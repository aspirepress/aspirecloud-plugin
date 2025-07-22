# AspireCloud

**Contributors:** AspirePress
**Tags:** wordpress, api, headless, passthrough, wordpress-api
**Requires at least:** 5.3
**Tested up to:** 6.8.1
**Requires PHP:** 7.4
**Stable tag:** 0.0.1
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

## Description

AspireCloud transforms your WordPress site into a headless installation with source API passthrough functionality. All requests to your site root are automatically passed through to source, while maintaining full WordPress admin functionality.

## Features

### Root-Level API Passthrough
- Direct passthrough from `https://yoursite.com/{path}` to `https://apisourcesite.com/{path}`
- GET requests only
- Query parameters preserved
- Error handling and response proxying

### Headless WordPress
- Frontend disabled for regular visitors
- Admin interface fully functional
- WordPress core paths protected
- Static assets served normally

### WordPress Integration
- Seamless integration with WordPress rewrite system
- Automatic activation/deactivation handling
- WordPress coding standards compliant
- Comprehensive error handling

## Quick Start

1. **Install & Activate** the plugin
2. **Visit Settings → Permalinks** and click "Save Changes" to flush rewrite rules
3. **Test** by visiting your site root - you should see the api welcome page content

## Protected WordPress Paths

These paths continue to work normally:
- `/wp-admin/` - WordPress admin
- `/wp-login.php` - Login page
- `/wp-content/` - Static assets
- `/wp-json/` - REST API

## Requirements

- **WordPress:** 5.3 or higher
- **PHP:** 7.4 or higher
- **Server:** Must support WordPress rewrite rules

## Development

This plugin follows WordPress coding standards and includes:
- PSR-4 autoloading
- PHPUnit tests
- PHPCS integration
- SCSS compilation for admin styles
- Comprehensive documentation

### Building Assets

#### Prerequisites
- Node.js 16+ and npm 8+
- Composer

#### Install Dependencies
```bash
npm install
composer install
```

#### Build Commands

**Production Build (compressed CSS):**
```bash
# Using npm
npm run build:css

# Using composer
composer build:css
```

**Development Build (expanded CSS with source maps):**
```bash
# Using npm
npm run build:css:dev

# Using composer
composer build:css:dev
```

**Watch Mode (auto-compile on changes):**
```bash
# Note: Watch mode may have issues on Windows. Use manual builds instead.
# Using npm
npm run watch:css

# Using composer
composer watch:css

# Alternative for Windows - direct command:
npx sass assets/css/admin.scss assets/css/admin.css --watch --style=expanded --source-map
```

**Full Build:**
```bash
# Using npm
npm run build

# Using composer
composer build
```

### Available Build Scripts

| Script | Description |
|--------|-------------|
| `build:css` | Compile SCSS to compressed CSS |
| `build:css:dev` | Compile SCSS to expanded CSS with source maps |
| `watch:css` | Watch SCSS files and auto-compile on changes |
| `build` | Run all build tasks |

## Support

For support and documentation, visit [AspirePress Documentation](https://docs.aspirepress.org/aspirecloud/).

## Changelog

### 0.0.1
- Initial release
- Root-level api.wordpress.org API passthrough
- Headless WordPress functionality
- WordPress admin preservation
