#!/bin/bash

# =============================================================================
# Queue Worker Setup Script for OS Manager
# Provides multiple options for running the queue worker automatically
# =============================================================================

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Auto-detect project path and user
if [ -d "/var/www/html/osmanagercl" ]; then
    PROJECT_PATH="/var/www/html/osmanagercl"  # Development
    USER="$(whoami)"
elif [ -d "/var/www/html/osmanager" ]; then
    PROJECT_PATH="/var/www/html/osmanager"  # Production
    USER="www-data"
else
    echo -e "${RED}Error: Could not detect project path${NC}"
    echo "Please edit this script and set PROJECT_PATH manually"
    exit 1
fi

echo -e "${BLUE}Detected environment:${NC}"
echo "  Path: $PROJECT_PATH"
echo "  User: $USER"
echo

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}    OS Manager Queue Worker Setup${NC}"
echo -e "${BLUE}========================================${NC}"
echo

# Function to setup cron job
setup_cron() {
    echo -e "${YELLOW}Setting up Cron Job for Queue Worker...${NC}"
    
    # Create the check script
    cat > /tmp/check-queue-worker.sh << 'EOF'
#!/bin/bash
# Check if queue worker is running, start if not

PROJECT_PATH="/var/www/html/osmanager"  # Update this path
LOG_FILE="$PROJECT_PATH/storage/logs/queue.log"

# Check if queue worker is already running
if ! pgrep -f "artisan queue:work" > /dev/null; then
    echo "[$(date)] Queue worker not running, starting..." >> "$LOG_FILE"
    cd "$PROJECT_PATH"
    nohup php artisan queue:work --sleep=3 --tries=3 --max-time=3600 >> "$LOG_FILE" 2>&1 &
    echo "[$(date)] Queue worker started with PID $!" >> "$LOG_FILE"
else
    echo "[$(date)] Queue worker is already running" >> "$LOG_FILE"
fi
EOF

    # Make it executable
    chmod +x /tmp/check-queue-worker.sh
    
    # Add to crontab (runs every minute)
    echo -e "${YELLOW}Adding cron job...${NC}"
    (crontab -l 2>/dev/null; echo "* * * * * /tmp/check-queue-worker.sh") | crontab -
    
    # Also add the Laravel scheduler if not present
    if ! crontab -l | grep -q "schedule:run"; then
        (crontab -l 2>/dev/null; echo "* * * * * cd $PROJECT_PATH && php artisan schedule:run >> /dev/null 2>&1") | crontab -
        echo -e "${GREEN}✓ Added Laravel scheduler to crontab${NC}"
    fi
    
    echo -e "${GREEN}✓ Cron job setup complete!${NC}"
    echo -e "${BLUE}The queue worker will be checked every minute and started if not running.${NC}"
    echo
    echo "To view cron jobs: crontab -l"
    echo "To view queue log: tail -f $PROJECT_PATH/storage/logs/queue.log"
}

# Function to setup systemd service
setup_systemd() {
    echo -e "${YELLOW}Setting up Systemd Service for Queue Worker...${NC}"
    
    # Create service file
    sudo tee /etc/systemd/system/osmanager-queue.service > /dev/null << EOF
[Unit]
Description=OS Manager Queue Worker
After=network.target

[Service]
User=$USER
Group=$USER
Restart=always
RestartSec=5
ExecStart=/usr/bin/php $PROJECT_PATH/artisan queue:work --sleep=3 --tries=3 --max-time=3600
WorkingDirectory=$PROJECT_PATH
StandardOutput=append:$PROJECT_PATH/storage/logs/queue.log
StandardError=append:$PROJECT_PATH/storage/logs/queue.log

[Install]
WantedBy=multi-user.target
EOF

    # Reload systemd and enable service
    echo -e "${YELLOW}Enabling and starting service...${NC}"
    sudo systemctl daemon-reload
    sudo systemctl enable osmanager-queue.service
    sudo systemctl start osmanager-queue.service
    
    echo -e "${GREEN}✓ Systemd service setup complete!${NC}"
    echo
    echo "Useful commands:"
    echo "  Status: sudo systemctl status osmanager-queue"
    echo "  Start:  sudo systemctl start osmanager-queue"
    echo "  Stop:   sudo systemctl stop osmanager-queue"
    echo "  Logs:   sudo journalctl -u osmanager-queue -f"
}

# Function to setup supervisor
setup_supervisor() {
    echo -e "${YELLOW}Setting up Supervisor for Queue Worker...${NC}"
    
    # Check if supervisor is installed
    if ! command -v supervisord &> /dev/null; then
        echo -e "${RED}Supervisor is not installed. Install it first:${NC}"
        echo "  Ubuntu/Debian: sudo apt-get install supervisor"
        echo "  CentOS/RHEL: sudo yum install supervisor"
        return 1
    fi
    
    # Create supervisor config
    sudo tee /etc/supervisor/conf.d/osmanager-queue.conf > /dev/null << EOF
[program:osmanager-queue]
process_name=%(program_name)s_%(process_num)02d
command=php $PROJECT_PATH/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=$USER
numprocs=1
redirect_stderr=true
stdout_logfile=$PROJECT_PATH/storage/logs/queue.log
stopwaitsecs=3600
EOF

    # Reload supervisor
    echo -e "${YELLOW}Reloading supervisor...${NC}"
    sudo supervisorctl reread
    sudo supervisorctl update
    sudo supervisorctl start osmanager-queue:*
    
    echo -e "${GREEN}✓ Supervisor setup complete!${NC}"
    echo
    echo "Useful commands:"
    echo "  Status: sudo supervisorctl status osmanager-queue:*"
    echo "  Start:  sudo supervisorctl start osmanager-queue:*"
    echo "  Stop:   sudo supervisorctl stop osmanager-queue:*"
    echo "  Logs:   tail -f $PROJECT_PATH/storage/logs/queue.log"
}

# Function to start queue worker manually
start_manual() {
    echo -e "${YELLOW}Starting Queue Worker Manually...${NC}"
    echo -e "${BLUE}This will run in the background and continue after you log out.${NC}"
    
    cd "$PROJECT_PATH"
    nohup php artisan queue:work --sleep=3 --tries=3 --max-time=3600 > storage/logs/queue.log 2>&1 &
    
    echo -e "${GREEN}✓ Queue worker started with PID $!${NC}"
    echo
    echo "To check if it's running: ps aux | grep 'artisan queue:work'"
    echo "To view logs: tail -f $PROJECT_PATH/storage/logs/queue.log"
    echo -e "${YELLOW}Note: This worker will stop if the server restarts!${NC}"
}

# Main menu
echo "Choose your setup method:"
echo
echo "1) Cron Job (Simple, checks every minute)"
echo "2) Systemd Service (Auto-starts on boot, recommended for modern Linux)"
echo "3) Supervisor (Most robust, handles crashes, recommended for production)"
echo "4) Manual Start (Temporary, good for testing)"
echo "5) Exit"
echo
read -p "Enter your choice [1-5]: " choice

case $choice in
    1)
        setup_cron
        ;;
    2)
        setup_systemd
        ;;
    3)
        setup_supervisor
        ;;
    4)
        start_manual
        ;;
    5)
        echo "Exiting..."
        exit 0
        ;;
    *)
        echo -e "${RED}Invalid choice!${NC}"
        exit 1
        ;;
esac

echo
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}    Setup Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo
echo "Your queue worker is now configured to run automatically."
echo "Visit /kds on your site to see the queue status indicator."
echo
echo -e "${BLUE}The KDS page will show:${NC}"
echo "  • Green indicator when queue is active"
echo "  • Red indicator when queue is inactive"
echo "  • Number of pending jobs"
echo "  • Last time orders were checked"