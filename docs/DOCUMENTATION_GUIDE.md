# Documentation Usage Guide

This guide helps AI assistants and developers understand how to properly use and maintain the documentation structure.

## 🤖 For AI Assistants (Claude, GPT, etc.)

### Initial Context
1. **Always start by reading `CLAUDE.md`** - This provides project context
2. **Check `docs/README.md`** for documentation overview
3. **Review `CONTRIBUTING.md`** for coding standards
4. **Look at `CHANGELOG.md`** for recent changes

### When Working on Features

#### Research Phase
```
1. Read CLAUDE.md for project context
2. Check docs/features/{feature-name}.md for existing documentation
3. Review related architecture docs if needed
4. Look at planning/ folder for future requirements
```

#### Implementation Phase
```
1. Follow patterns in CONTRIBUTING.md
2. Update CHANGELOG.md in Unreleased section
3. Update relevant feature documentation
4. Create new docs only if feature is entirely new
```

#### Documentation Updates
```
1. Edit existing feature docs rather than CLAUDE.md
2. Use templates from docs/templates/ for new documentation
3. Keep CLAUDE.md lean - only high-level context
4. Update indexes when adding new documentation
```

### Common Scenarios

#### "How do I implement X?"
1. Check if X exists in `docs/features/`
2. Look for related features in documentation index
3. Review `docs/architecture/` for system patterns
4. Check `planning/upcoming/` for planned implementations

#### "Where should I document this?"
- **New feature**: Create `docs/features/feature-name.md` using template
- **API endpoint**: Add to `docs/api/endpoints.md`
- **Bug fix**: Note in CHANGELOG.md only
- **Architecture change**: Update `docs/architecture/`
- **Future plan**: Create in `planning/upcoming/`

#### "What's the pattern for Y?"
1. Check `CONTRIBUTING.md` for coding standards
2. Look at similar features in `docs/features/`
3. Review `docs/architecture/overview.md` for patterns
4. Search codebase for existing examples

### Documentation Principles

#### DO:
- ✅ Keep documentation close to the code it describes
- ✅ Update docs in the same PR as code changes
- ✅ Use templates for consistency
- ✅ Cross-reference related documentation
- ✅ Include code examples

#### DON'T:
- ❌ Add detailed feature docs to CLAUDE.md
- ❌ Create duplicate documentation
- ❌ Leave TODO comments in docs
- ❌ Document obvious code
- ❌ Forget to update indexes

## 📝 Documentation Structure Map

```
For AI Context & Overview:
├── CLAUDE.md            → Project context for AI
├── README.md           → Project overview
├── CONTRIBUTING.md     → How to contribute
└── CHANGELOG.md        → What has changed

For Feature Information:
docs/features/
├── pos-integration.md      → POS system details
├── delivery-system.md      → Delivery workflow
├── pricing-system.md       → Pricing logic
├── supplier-integration.md → Supplier connectivity
├── vat-dashboard.md        → VAT return management
├── cash-reconciliation.md  → Cash counting system
└── {new-feature}.md       → Use feature template

For Technical Details:
docs/
├── architecture/       → System design
├── development/       → Dev guides
├── deployment/        → Production info
└── api/              → API reference

For Planning:
planning/
├── upcoming/         → Future features
└── completed/        → Implemented plans
```

## 🎯 Quick Decision Tree

### "I need to document something new"

```
Is it a new feature?
├─ Yes → Create docs/features/{name}.md using template
└─ No
   │
   Is it an API endpoint?
   ├─ Yes → Add to docs/api/endpoints.md
   └─ No
      │
      Is it a bug fix?
      ├─ Yes → Add to CHANGELOG.md only
      └─ No
         │
         Is it architecture/pattern?
         ├─ Yes → Update docs/architecture/
         └─ No → Ask for guidance
```

### "I need to find information"

```
What type of information?
├─ How the system works → docs/architecture/
├─ How a feature works → docs/features/
├─ How to develop → docs/development/
├─ How to deploy → docs/deployment/
├─ API details → docs/api/
└─ Future plans → planning/
```

## 💡 Best Practices

### 1. Keep Documentation Current
```bash
# When making changes:
1. Update code
2. Update tests
3. Update documentation
4. Update CHANGELOG.md
```

### 2. Use Consistent Format
- Follow templates in `docs/templates/`
- Use clear headings and sections
- Include code examples
- Add troubleshooting sections

### 3. Cross-Reference
```markdown
<!-- Good -->
For details on price calculations, see [Pricing System](../features/pricing-system.md).

<!-- Bad -->
For details on price calculations, see the pricing documentation.
```

### 4. Version Documentation
- Note version/date when behavior changes
- Keep historical context when needed
- Use CHANGELOG.md for tracking changes

## 📋 Checklist for AI Assistants

Before completing a task:
- [ ] Have I checked existing documentation?
- [ ] Have I updated relevant documentation?
- [ ] Have I added to CHANGELOG.md if needed?
- [ ] Have I used the correct template?
- [ ] Have I updated any indexes?
- [ ] Have I kept CLAUDE.md focused?
- [ ] Have I cross-referenced related docs?

## 🚨 Common Mistakes to Avoid

1. **Adding feature details to CLAUDE.md**
   - ❌ Wrong: Adding pricing algorithm details to CLAUDE.md
   - ✅ Right: Update docs/features/pricing-system.md

2. **Creating new files unnecessarily**
   - ❌ Wrong: Creating new doc for minor feature update
   - ✅ Right: Update existing feature documentation

3. **Forgetting to update indexes**
   - ❌ Wrong: Add new doc without updating docs/README.md
   - ✅ Right: Update all relevant index files

4. **Duplicating information**
   - ❌ Wrong: Same info in multiple places
   - ✅ Right: Single source of truth with cross-references

5. **Mixing planning with documentation**
   - ❌ Wrong: Future features in docs/features/
   - ✅ Right: Future features in planning/upcoming/

## 📞 Getting Help

If unsure about documentation:
1. Check this guide
2. Look at existing examples
3. Use the templates
4. Ask in the PR/issue
5. Default to updating existing docs

Remember: Good documentation is crucial for project maintainability and AI assistant effectiveness!