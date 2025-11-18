# FCHub Stream Plugin

![Version](https://img.shields.io/badge/version-0.9.1-blue)
![License](https://img.shields.io/badge/license-Proprietary%20(Beta)-orange)
![PHP](https://img.shields.io/badge/PHP-8.3%2B-777bb4)
![WordPress](https://img.shields.io/badge/WordPress-6.7%2B-21759b)
![Status](https://img.shields.io/badge/status-beta-yellow)
![Requires](https://img.shields.io/badge/requires-FluentCommunity-00a0d2)
![Downloads](https://img.shields.io/github/downloads/vcode-sh/fchub-stream-public/total)

Real-time streaming plugin for FluentCommunity powered by fchub infrastructure.

## 📚 Documentation

- **[TODO & Implementation Plan](./todo/TODO.md)** - Complete implementation plan with tasks
- **[Knowledge Base](./knowledgebase/README.md)** - Technical documentation and analysis

### Quick Links

- [Backend Verification](./knowledgebase/backend-verification.md)
- [Frontend UI/UX Plan](./knowledgebase/frontend-ui-ux-plan.md)
- [Portal Integration](./knowledgebase/portal-integration.md)
- [FluentCommunity Analysis](./knowledgebase/fluentcommunity-analysis.md)

## Installation

The plugin is automatically mounted in Docker via `docker-compose.yml`. Make sure the plugin is activated in WordPress Admin.

## Structure

- `app/` - Main application code
  - `Core/` - Core application classes
  - `Admin/` - Admin interface classes
  - `Hooks/` - WordPress hooks and handlers
  - `Http/` - Controllers and routes
  - `Services/` - Business logic services
  - `Utils/` - Utility classes
  - `Models/` - Data models
- `admin/` - Admin templates
- `admin-app/` - Vue.js admin interface
- `boot/` - Bootstrap files
- `config/` - Configuration files
- `tests/` - PHPUnit tests

## Requirements

- WordPress 6.7+
- PHP 8.3+
- FluentCommunity plugin (must be active)

## Development

### Code Quality

```bash
# Run PHPCS linting
composer lint

# Auto-fix coding standards
composer fix

# Run tests
composer test
```

### Vue Admin App

```bash
cd admin-app
npm install
npm run dev    # Development mode
npm run build  # Production build
```

## WordPress Coding Standards

This plugin follows WordPress Coding Standards:
- PHP 8.3+ compatibility
- PSR-12 compliance
- Short array syntax enforced
- Proper naming conventions
- Modular architecture

## Architecture

The plugin follows a modular architecture similar to FluentCommunity Companion:
- Separation of concerns
- Single responsibility principle
- Dependency injection ready
- Testable components
