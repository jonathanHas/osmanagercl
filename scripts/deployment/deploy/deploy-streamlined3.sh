#!/bin/bash

# =============================================================================
# OS Manager Streamlined Deployment Script — v3
# Laravel 12 application with MySQL database
#
# Changes from deploy-streamlined2.sh:
#   1. rsync gains --omit-dir-times. `-a` implies `-t`, and setting an explicit mtime requires
#      OWNING the path. Production directories are owned by www-data (this script chowns them),
#      so every pre-existing directory produced "failed to set times: Operation not permitted"
#      and rsync exited 23 — which the exit-code guard correctly treated as fatal, aborting the
#      deploy before migrations. File contents were never affected; only directory timestamps.
#   2. public/storage and public/hot are excluded from the sync. The staging dir is a git
#      checkout where public/storage is gitignored, so --delete removed the storage symlink on
#      EVERY deploy. It was supposed to be recreated by `php artisan storage:link` at the end,
#      but that ran after the chown and was silenced with `2>/dev/null || true`, so it failed
#      invisibly every time. public/hot is the Vite dev-server marker — if it ever reaches
#      production every asset URL breaks.
#   3. The rsync exit-code guard now classifies exit 23. Attribute-only failures (times,
#      permissions, owner, group) warn and continue; anything else still aborts.
#   4. The storage symlink is created with sudo and verified, instead of being silenced.
#   5. sudo availability is probed before the chown/chmod block. The post-deploy ssh runs with
#      no tty, so if jon does not have passwordless sudo on the server those commands fail
#      silently. Now it says so, loudly, instead of pretending the permissions were set.
#
# 2026-08-08 — the ownership model itself was wrong:
#   6. The post-deploy `chown -R www-data:www-data . && chmod -R 755 .` handed the entire tree to a
#      user this script is not. The moment it actually ran, the NEXT rsync could not create its temp
#      files ("mkstemp ... Permission denied", exit 23, nothing transferred) and every remaining
#      post-deploy step — composer install, config/route/view:cache, migrate — lost write access too.
#      It had only ever appeared harmless because sudo was unavailable and it silently did nothing.
#      Replaced with the standard Laravel layout: the tree is owned by $PROD_USER with group
#      www-data, and only storage/ and bootstrap/cache/ are group-writable, because those are the
#      only paths the web server writes. Ownership is now one-time setup, not a per-deploy action.
#   7. check_production_permissions() runs before rsync and aborts with the exact repair commands if
#      the tree is not writable, instead of failing mid-transfer with one mkstemp line per file.
#   8. A genuine exit 23 now explains itself when the cause is mkstemp/Permission denied.
# =============================================================================

# === CONFIGURE THESE ===
DEV_DIR=/var/www/html/osmanagercl               # where you write code
DEPLOY_DIR=~/deployments/osmanagercl            # clean staging copy
PROD_USER=jon
PROD_HOST=lilThink2
PROD_PATH=/var/www/html/osmanager

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Returns 0 if every rsync error in the given file is an attribute-setting failure (times,
# permissions, owner, group) rather than a real transfer failure. Used to decide whether exit
# code 23 is benign. Per-file errors are formatted "rsync: [generator] failed to set times on ..."
# — the trailing summary line starts "rsync error:" and is deliberately not matched here.
rsync_errors_are_attr_only() {
    local errfile="$1"

    [[ -s "$errfile" ]] || return 0

    ! grep -E '^rsync: ' "$errfile" \
        | grep -qvE 'failed to set (times|modification time|permissions|owner|group)|chgrp|chown'
}

# Check if host is reachable
check_host_connectivity() {
    log "🌐 Checking if $PROD_HOST is reachable..."

    # -n is load-bearing: without it ssh inherits this script's stdin and reads it to EOF, which
    # swallows every later `read` answer (branch confirmation, commit message). Typing the answers
    # by hand hides it — the prompt appears before you type, so there is nothing buffered to steal —
    # but it makes the script impossible to drive from a pipe and eats any type-ahead. The other ssh
    # calls are safe already: each supplies its own stdin via a heredoc.
    if ! ssh -n -o ConnectTimeout=5 "$PROD_USER@$PROD_HOST" 'exit' 2>/dev/null; then
        error "Cannot connect to $PROD_HOST via SSH. Aborting deployment."
        exit 1
    fi

    success "$PROD_HOST is reachable."
}

# Print the one-time repair for a production tree the deploying user cannot write.
#
# The web server only needs to READ application code — the only paths Laravel writes at runtime are
# storage/ and bootstrap/cache/. So the tree belongs to $PROD_USER with group www-data, not to
# www-data outright. Chowning everything to www-data is what locks the deploying user out.
print_permission_repair() {
    echo "     sudo chown -R $PROD_USER:www-data $PROD_PATH"
    echo "     sudo find $PROD_PATH -type d -exec chmod 755 {} +"
    echo "     sudo find $PROD_PATH -type f -exec chmod 644 {} +"
    echo "     sudo chmod -R 775 $PROD_PATH/storage $PROD_PATH/bootstrap/cache"
    echo "     sudo find $PROD_PATH/storage $PROD_PATH/bootstrap/cache -type d -exec chmod g+s {} +"
    echo "     sudo chmod +x $PROD_PATH/artisan"
}

# Verify the deploying user can actually write to production BEFORE rsync starts.
#
# Without this, a tree owned by www-data:www-data with mode 755 produces one "mkstemp ... Permission
# denied" line per file and exit 23 — which looks like the benign attribute noise this script already
# tolerates, but means nothing transferred. Every post-deploy step is affected too: composer install,
# config:cache, view:cache and migrate all run as $PROD_USER and all need to write.
check_production_permissions() {
    log "🔐 Checking production write access..."

    local unwritable
    unwritable=$(ssh "$PROD_USER@$PROD_HOST" bash -s -- "$PROD_PATH" << 'EOF'
        PROD_PATH="$1"
        for dir in "$PROD_PATH" "$PROD_PATH/public/build/assets" "$PROD_PATH/vendor/composer" \
                   "$PROD_PATH/storage/framework/views" "$PROD_PATH/bootstrap/cache"; do
            [ -d "$dir" ] || continue
            [ -w "$dir" ] || echo "$dir"
        done
EOF
    )
    local probe_status=$?

    if [[ $probe_status -ne 0 ]]; then
        error "Could not check production permissions (ssh exited $probe_status). Aborting."
        exit 1
    fi

    if [[ -n "$unwritable" ]]; then
        error "$PROD_USER cannot write to these production directories:"
        echo "$unwritable" | sed 's/^/       /'
        echo ""
        echo "   rsync would fail with \"mkstemp ... Permission denied\" and nothing would transfer."
        echo "   This is what a 'chown -R www-data:www-data' over the whole tree does. Repair it once"
        echo "   on the server (interactive, so sudo can prompt for a password):"
        print_permission_repair
        exit 1
    fi

    success "Production tree is writable by $PROD_USER."
}

# Copy and validate environment file
setup_environment() {
    log "🔧 Setting up environment configuration..."

    cd "$DEPLOY_DIR" || exit 1

    # Copy .env from development directory if it doesn't exist
    if [[ ! -f .env ]]; then
        if [[ -f "$DEV_DIR/.env" ]]; then
            log "Copying .env from development directory..."
            cp "$DEV_DIR/.env" .env
        else
            error "No .env file found in development directory. Cannot proceed."
            exit 1
        fi
    fi

    # Validate critical environment variables
    log "Validating environment configuration..."

    if ! grep -q "^DB_CONNECTION=" .env; then
        error "DB_CONNECTION not found in .env file."
        exit 1
    fi

    if ! grep -q "^DB_DATABASE=" .env; then
        error "DB_DATABASE not found in .env file."
        exit 1
    fi

    DB_CONNECTION=$(grep "^DB_CONNECTION=" .env | cut -d'=' -f2 | tr -d '"' | tr -d "'")

    if [[ "$DB_CONNECTION" != "mysql" ]]; then
        warning "Database connection is set to '$DB_CONNECTION', expected 'mysql'."
    fi

    success "Environment configuration validated."
}

# Validate MySQL database connectivity
check_database_connectivity() {
    log "🗄️ Checking MySQL database connectivity..."

    cd "$DEPLOY_DIR" || exit 1

    # Test main database connection
    log "Testing main database connection..."
    if php artisan tinker --execute="try { DB::connection()->getPdo(); echo 'Main DB: Connected successfully'; } catch (Exception \$e) { echo 'Main DB Error: ' . \$e->getMessage(); throw \$e; }" 2>/dev/null; then
        success "Main database connection verified."
    else
        error "Cannot connect to main MySQL database. Check your .env configuration."
        error "Database: $(grep '^DB_DATABASE=' .env | cut -d'=' -f2)"
        error "Host: $(grep '^DB_HOST=' .env | cut -d'=' -f2)"
        error "Username: $(grep '^DB_USERNAME=' .env | cut -d'=' -f2)"
        exit 1
    fi

    # Test POS database connection (optional)
    log "Testing POS database connection..."
    if grep -q "^POS_DB_DATABASE=" .env && grep -q "^POS_DB_USERNAME=" .env; then
        if php artisan tinker --execute="try { DB::connection('pos')->getPdo(); echo 'POS DB: Connected successfully'; } catch (Exception \$e) { echo 'POS DB: ' . \$e->getMessage(); }" 2>/dev/null | grep -q "Connected successfully"; then
            success "POS database connection verified."
        else
            warning "POS database connection failed or not properly configured."
        fi
    else
        log "POS database not configured, skipping connection test."
    fi
}

# Main deployment function
main() {
    # Create deployment log file
    DEPLOY_LOG="deploy_$(date +%Y%m%d_%H%M%S).log"
    log "🚀 Starting OS Manager streamlined deployment to $PROD_HOST..."
    log "📝 Deployment log will be saved to: $DEPLOY_LOG"

    # Step 1: Check connectivity and that we can actually write to production
    check_host_connectivity
    check_production_permissions

    # Step 2: Detect current branch
    log "🌿 Detecting current git branch..."
    cd "$DEV_DIR" || exit 1

    CURRENT_BRANCH=$(git branch --show-current)
    if [[ -z "$CURRENT_BRANCH" ]]; then
        error "Could not detect current git branch."
        exit 1
    fi

    success "Current branch: $CURRENT_BRANCH"

    # Check if branch exists on remote
    if ! git ls-remote --heads origin "$CURRENT_BRANCH" | grep -q "$CURRENT_BRANCH"; then
        error "Branch '$CURRENT_BRANCH' does not exist on remote origin."
        log "Available remote branches:"
        git ls-remote --heads origin
        exit 1
    fi

    # Confirm deployment branch with user
    echo
    warning "⚠️  You are about to deploy from branch: $CURRENT_BRANCH"
    read -p "Continue with deployment from this branch? [y/N] " CONFIRM_BRANCH

    if [[ $CONFIRM_BRANCH != "y" && $CONFIRM_BRANCH != "Y" ]]; then
        error "Deployment cancelled by user."
        exit 1
    fi

    # Step 3: Git commit in DEV_DIR
    log "🔄 Committing latest changes from development directory..."
    cd "$DEV_DIR" || exit 1

    git status
    echo
    read -p "Enter commit message: " COMMIT_MSG

    if [[ -z "$COMMIT_MSG" ]]; then
        error "Commit message cannot be empty."
        exit 1
    fi

    git add -A

    # Update version marker before committing (use latest git commit hash)
    if [[ -f "$DEV_DIR/.env" ]]; then
        NEW_VERSION=$(git rev-parse --short HEAD)
        if grep -q "^APP_VERSION=" "$DEV_DIR/.env"; then
            # Rewrite .env in place WITHOUT replacing its inode. `sed -i` writes a
            # temp file and renames it over the original; it restores the mode but
            # its chgrp back to www-data silently fails when the deploying user is
            # not a www-data member. .env then ends up jon:jon 0640, Apache can no
            # longer read it, and every page 500s with MissingAppKeyException.
            # Truncating the existing file with `>` keeps owner, group and mode.
            ENV_TMP=$(mktemp)
            if sed -E "s/^APP_VERSION=.*/APP_VERSION=${NEW_VERSION}/" "$DEV_DIR/.env" > "$ENV_TMP" && [[ -s "$ENV_TMP" ]]; then
                cat "$ENV_TMP" > "$DEV_DIR/.env"
                log "Updated APP_VERSION in .env to $NEW_VERSION"
            else
                error "Failed to rewrite APP_VERSION in .env; leaving it unchanged."
            fi
            rm -f "$ENV_TMP"
        else
            echo "APP_VERSION=${NEW_VERSION}" >> "$DEV_DIR/.env"
            log "Appended APP_VERSION=${NEW_VERSION} to .env"
        fi
        git add .env
    fi
    git commit -m "$COMMIT_MSG"
    git push origin "$CURRENT_BRANCH"

    success "Changes committed and pushed."

    # Step 4: Update deployment directory
    log "📥 Updating clean deployment directory..."

    if [[ ! -d "$DEPLOY_DIR" ]]; then
        log "Creating deployment directory..."
        mkdir -p "$DEPLOY_DIR"
        cd "$DEPLOY_DIR" || exit 1
        git clone "$DEV_DIR" .
        git checkout "$CURRENT_BRANCH"
    else
        cd "$DEPLOY_DIR" || exit 1

        # Check current branch in deployment directory
        DEPLOY_CURRENT_BRANCH=$(git branch --show-current)

        if [[ "$DEPLOY_CURRENT_BRANCH" != "$CURRENT_BRANCH" ]]; then
            warning "Deployment directory is on branch '$DEPLOY_CURRENT_BRANCH', need to switch to '$CURRENT_BRANCH'"

            # Fetch latest changes first
            git fetch origin

            # Check if target branch exists locally
            if git branch | grep -q "^\s*$CURRENT_BRANCH$"; then
                log "Switching to existing local branch '$CURRENT_BRANCH'..."
                git checkout "$CURRENT_BRANCH"
            else
                log "Creating and switching to new local branch '$CURRENT_BRANCH'..."
                git checkout -b "$CURRENT_BRANCH" "origin/$CURRENT_BRANCH"
            fi
        fi

        # Now pull the latest changes
        log "Pulling latest changes from origin/$CURRENT_BRANCH..."
        if ! git pull origin "$CURRENT_BRANCH"; then
            error "Failed to pull from origin/$CURRENT_BRANCH"
            warning "This might be due to conflicts. Offering to reset deployment directory..."

            echo
            read -p "Reset deployment directory to clean state? [y/N] " RESET_DEPLOY

            if [[ $RESET_DEPLOY == "y" || $RESET_DEPLOY == "Y" ]]; then
                log "Resetting deployment directory..."
                cd "$(dirname "$DEPLOY_DIR")" || exit 1
                rm -rf "$DEPLOY_DIR"
                mkdir -p "$DEPLOY_DIR"
                cd "$DEPLOY_DIR" || exit 1
                git clone "$DEV_DIR" .
                git checkout "$CURRENT_BRANCH"
                success "Deployment directory reset and updated."
            else
                error "Cannot continue with conflicted deployment directory."
                exit 1
            fi
        fi
    fi

    # Verify deployment directory state
    log "📋 Verifying deployment directory state..."
    echo "Current branch: $(git branch --show-current)"
    echo "Latest commit: $(git log --oneline -1)"
    echo "Remote tracking: $(git branch -vv | grep '^\*')"

    success "Deployment directory updated to branch '$CURRENT_BRANCH'."

    # Step 5: Install production dependencies
    log "📦 Installing production dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction

    success "Dependencies installed."

    # Step 6: Setup environment and check database connectivity
    setup_environment
    check_database_connectivity

    # Step 7: Build frontend assets (always)
    log "🎨 Building frontend assets..."
    npm install --production=false
    npm run build
    success "Frontend assets built."

    # Step 8: Rsync to production
    log "📡 Syncing files to production..."
    # WARNING: --delete removes files on the destination that do not exist in the source. Anything
    # that lives only on production (venv, the storage symlink) MUST be excluded or it is destroyed
    # on every run.
    #
    # --no-perms/--no-owner/--no-group/--omit-dir-times: production manages its own ownership and
    # modes (see check_production_permissions), so rsync has no business setting attributes. Any path
    # it does not own produces an "Operation not permitted" line and a bump to exit code 23 — file
    # contents transfer fine, but the noise buried real errors and made the exit code useless.
    # File mtimes are still preserved (rsync writes a temp file it owns, then renames), so
    # incremental syncs stay fast. Only directory times are skipped.
    RSYNC_ERR=$(mktemp)
    rsync -avz --no-perms --no-owner --no-group --omit-dir-times --delete \
        --exclude='.env' \
        --exclude='.git' \
        --exclude='node_modules' \
        --exclude='tests' \
        --exclude='public/storage' \
        --exclude='public/hot' \
        --exclude='storage/logs/*' \
        --exclude='storage/app/*' \
        --exclude='storage/framework/cache/*' \
        --exclude='storage/framework/sessions/*' \
        --exclude='storage/framework/views/*' \
        --exclude='bootstrap/cache/*' \
        --exclude='scripts/invoice-parser/venv' \
        --exclude='__pycache__/' \
        --exclude='*.pyc' \
        --exclude='*.log' \
        --exclude='*.sqlite' \
        "$DEPLOY_DIR/" "$PROD_USER@$PROD_HOST:$PROD_PATH" 2>"$RSYNC_ERR"
    RSYNC_STATUS=$?

    # stderr was captured rather than teed so the exit code can be classified below; replay it now
    # so nothing is hidden from the deploy log.
    [[ -s "$RSYNC_ERR" ]] && cat "$RSYNC_ERR" >&2

    # The exit code used to be ignored entirely, so a failed sync still went on to run migrations
    # against a half-updated tree. 24 (source files vanished mid-transfer) is benign. 23 is benign
    # ONLY when every error was an attribute-setting failure — file contents are then intact.
    # Anything else is a real transfer failure and must stop the deploy.
    if [[ $RSYNC_STATUS -eq 24 ]]; then
        warning "rsync reported vanished source files (exit 24) — continuing."
    elif [[ $RSYNC_STATUS -eq 23 ]] && rsync_errors_are_attr_only "$RSYNC_ERR"; then
        warning "rsync exit 23 was attribute-only (file contents transferred) — continuing."
    elif [[ $RSYNC_STATUS -ne 0 ]]; then
        error "rsync failed with exit code $RSYNC_STATUS. Aborting before post-deployment tasks."
        # "mkstemp ... Permission denied" is not attribute noise — rsync could not create its temp
        # file, so those files did not transfer at all. It means the destination directories are not
        # writable by $PROD_USER, almost always because something chowned the tree to www-data.
        if grep -q 'mkstemp.*Permission denied' "$RSYNC_ERR"; then
            echo ""
            echo "   Those 'mkstemp ... Permission denied' lines mean $PROD_USER cannot create files"
            echo "   in the destination directories — the listed files did NOT transfer. Repair once"
            echo "   on the server (interactive, so sudo can prompt):"
            print_permission_repair
        fi
        rm -f "$RSYNC_ERR"
        exit 1
    fi

    rm -f "$RSYNC_ERR"
    success "Files synced to production."

    # Step 9: Run post-deployment tasks on server
    log "⚙️ Running post-deployment tasks on production server..."

    # The commit hash of what we are actually shipping. It has to be computed HERE: production
    # has no .git (the rsync above excludes it), so `git rev-parse` on the server would find
    # nothing.
    BUILD_HASH=$(git -C "$DEPLOY_DIR" rev-parse --short HEAD 2>/dev/null)
    if [[ -z "$BUILD_HASH" ]]; then
        BUILD_HASH=$(date +%Y%m%d%H%M%S)
    fi

    # NOTE: the delimiter is quoted ('EOF') so this block is sent to the server verbatim. With an
    # unquoted delimiter the local shell expanded everything first, which silently gutted the
    # build-version step below: $dir and $value inside the single-quoted php -r were substituted
    # away locally, leaving "php -r ' = getcwd()...'" — a parse error on every single deploy — and
    # DEPLOY_BUILD_VERSION arrived empty so the marker only ever got a timestamp, never the hash.
    # Values that must come from this machine are passed as positional arguments instead.
    ssh "$PROD_USER@$PROD_HOST" bash -s -- "$PROD_PATH" "$BUILD_HASH" << 'EOF'
        PROD_PATH="$1"
        BUILD_HASH="$2"
        cd "$PROD_PATH"

        echo "📦 Installing composer dependencies on production..."
        composer install --no-dev --optimize-autoloader --no-interaction

        echo "🏷️ Recording build version..."
        export DEPLOY_BUILD_VERSION="$BUILD_HASH"
        php -r '$dir = getcwd()."/storage/app";
            if (!is_dir($dir)) { mkdir($dir, 0775, true); }
            $value = getenv("DEPLOY_BUILD_VERSION");
            if ($value === false || $value === "") { $value = date("YmdHis"); }
            file_put_contents($dir."/build-version", $value);'
        unset DEPLOY_BUILD_VERSION
        echo "Build version set to ${BUILD_HASH}"

        echo "🐍 Installing Python parser dependencies..."
        cd scripts/invoice-parser
        if [ -d "venv" ]; then
            venv/bin/pip install -r requirements.txt --quiet
        fi
        cd "$PROD_PATH"

        # NOT a chown to www-data:www-data. That is what broke the deploy on 2026-08-08: it hands the
        # whole tree to a user this script is not, so the next rsync cannot create its temp files
        # ("mkstemp ... Permission denied") and every step below here — composer install, the artisan
        # caches, migrate — loses write access too. It only ever appeared to work because sudo was
        # unavailable and the block silently did nothing.
        #
        # The web server needs to READ code and WRITE only storage/ and bootstrap/cache/. So the tree
        # stays owned by the deploying user with group www-data, and only those two paths are
        # group-writable. Ownership is a one-time setup (see print_permission_repair); all this does
        # per-deploy is re-assert group-write on the two runtime paths, which rsync can clear on
        # newly created files.
        echo "🔐 Ensuring runtime paths are writable by the web server..."
        if chmod -R g+w storage bootstrap/cache 2>/dev/null; then
            echo "✅ storage and bootstrap/cache are group-writable"
        else
            echo "❌ Could not set group-write on storage / bootstrap/cache."
            echo "   The web server will fail to write sessions, caches and compiled views."
            echo "   Repair on the server:"
            echo "     sudo chown -R $(id -un):www-data $PROD_PATH"
            echo "     sudo chmod -R 775 $PROD_PATH/storage $PROD_PATH/bootstrap/cache"
        fi

        # public/storage is excluded from the rsync so it survives deploys, but recreate it if it is
        # genuinely missing. With public/ owned by the deploying user this no longer needs sudo — v2
        # required it only because the chown had handed public/ to www-data, and hid the resulting
        # "symlink(): Permission denied" behind `2>/dev/null || true` so the link silently never existed.
        echo "🔗 Ensuring storage link exists..."
        if [ -L public/storage ] || [ -d public/storage ]; then
            echo "✅ public/storage already present"
        elif ln -sfn "$PROD_PATH/storage/app/public" public/storage 2>/dev/null; then
            echo "✅ public/storage symlink created"
        elif sudo -n ln -sfn "$PROD_PATH/storage/app/public" public/storage 2>/dev/null; then
            echo "✅ public/storage symlink created (via sudo)"
        else
            echo "❌ public/storage is MISSING and could not be created — uploaded files will 404."
            echo "   Run manually: sudo ln -sfn $PROD_PATH/storage/app/public $PROD_PATH/public/storage"
        fi

        echo "🧹 Clearing application caches..."
        php artisan config:clear
        php artisan route:clear
        php artisan view:clear
        php artisan cache:clear
        php artisan queue:clear

        echo "⚡ Optimizing application for production..."
        php artisan config:cache
        php artisan route:cache
        php artisan view:cache

        echo "🗃️ Running database migrations..."
        php artisan migrate --force

        echo "🏷️ Syncing label templates..."
        php artisan label:sync-templates

        echo "🔄 Restarting queue workers..."
        php artisan queue:restart

        echo "📊 Checking application status..."
        php artisan about --only=environment,cache,database
EOF

    success "Post-deployment tasks completed."

    # Step 10: Final verification including label templates
    log "🔍 Running final verification..."

    ssh "$PROD_USER@$PROD_HOST" bash -s -- "$PROD_PATH" "$BUILD_HASH" "$CURRENT_BRANCH" << 'EOF'
        PROD_PATH="$1"
        BUILD_HASH="$2"
        CURRENT_BRANCH="$3"
        cd "$PROD_PATH"

        echo "📋 Production server verification:"
        # Production is not a git checkout (the rsync excludes .git), so the branch and commit are
        # reported from the deploying machine and confirmed against the build-version marker that
        # was just written. That marker is the only on-server record of what code is live.
        echo "Deployed branch: ${CURRENT_BRANCH}"
        echo "Deployed commit: ${BUILD_HASH}"
        echo "Build version marker: $(cat storage/app/build-version 2>/dev/null || echo 'MISSING')"
        if [[ "$(cat storage/app/build-version 2>/dev/null)" == "$BUILD_HASH" ]]; then
            echo "✅ Build version marker matches the deployed commit"
        else
            echo "❌ Build version marker does not match — check the build version step above"
        fi
        echo "Application environment: $(php artisan env 2>/dev/null || echo 'Unknown')"

        echo "Checking storage symlink..."
        if [[ -L public/storage ]]; then
            echo "✅ public/storage -> $(readlink public/storage)"
        else
            echo "❌ public/storage symlink is missing — uploaded files will 404"
        fi

        echo "Checking Vite dev marker is absent..."
        if [[ -f public/hot ]]; then
            echo "❌ public/hot exists on production — assets will point at a dev server. Delete it."
        else
            echo "✅ No public/hot marker"
        fi

        echo "Testing database connectivity..."
        if php artisan tinker --execute="DB::connection()->getPdo(); echo 'Database: OK';" 2>/dev/null; then
            echo "✅ Database connection successful"
        else
            echo "❌ Database connection failed"
        fi

        echo "Checking label templates..."
        php artisan label:sync-templates --show

        echo "Checking if application is responding..."
        if php artisan route:list | head -1 >/dev/null 2>&1; then
            echo "✅ Application routes loaded successfully"
        else
            echo "❌ Application may have issues"
        fi
EOF

    success "Deployment verification completed."

    # Step 11: Completion notification
    log "🎉 Deployment to $PROD_HOST completed successfully!"

    # Play notification sound as required by CLAUDE.md
    if command -v mpg123 >/dev/null 2>&1; then
        mpg123 /home/jon/Music/notification.mp3 2>/dev/null || true
    fi

    # Display summary
    echo
    echo "=========================================="
    echo "         DEPLOYMENT SUMMARY"
    echo "=========================================="
    echo "Source: $DEV_DIR"
    echo "Target: $PROD_USER@$PROD_HOST:$PROD_PATH"
    echo "Time: $(date)"
    echo "Commit: $COMMIT_MSG"
    echo "Branch: $CURRENT_BRANCH"
    echo ""
    echo "✅ Database migrations applied"
    echo "✅ Label templates synced"
    echo "✅ Frontend assets built"
    echo "✅ Application optimized"
    echo "=========================================="
}

# Dry-run the rsync only — shows what WOULD transfer and delete, touches nothing.
dry_run() {
    log "🧪 Dry-run rsync to $PROD_HOST (no changes will be made)..."
    rsync -avzn --no-perms --no-owner --no-group --omit-dir-times --delete \
        --exclude='.env' \
        --exclude='.git' \
        --exclude='node_modules' \
        --exclude='tests' \
        --exclude='public/storage' \
        --exclude='public/hot' \
        --exclude='storage/logs/*' \
        --exclude='storage/app/*' \
        --exclude='storage/framework/cache/*' \
        --exclude='storage/framework/sessions/*' \
        --exclude='storage/framework/views/*' \
        --exclude='bootstrap/cache/*' \
        --exclude='scripts/invoice-parser/venv' \
        --exclude='__pycache__/' \
        --exclude='*.pyc' \
        --exclude='*.log' \
        --exclude='*.sqlite' \
        "$DEPLOY_DIR/" "$PROD_USER@$PROD_HOST:$PROD_PATH"
    log "Dry-run complete. Expect no 'failed to set times' lines and no 'deleting public/storage'."
}

# Script usage
usage() {
    echo "Usage: $0 [deploy|dry-run|help]"
    echo "  deploy   Full deployment (default)"
    echo "  dry-run  Rsync in --dry-run mode only; makes no changes to production"
}

# Handle script arguments
case "${1:-deploy}" in
    "deploy"|"")
        # Redirect all output to both terminal and log file
        main 2>&1 | tee "deploy_$(date +%Y%m%d_%H%M%S).log"
        ;;
    "dry-run"|"--dry-run"|"-n")
        dry_run 2>&1 | tee "dryrun_$(date +%Y%m%d_%H%M%S).log"
        ;;
    "help"|"--help"|"-h")
        usage
        exit 0
        ;;
    *)
        error "Unknown command: $1"
        usage
        exit 1
        ;;
esac
