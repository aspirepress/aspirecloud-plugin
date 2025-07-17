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
- Comprehensive documentation

## Support

For support and documentation, visit [AspirePress Documentation](https://docs.aspirepress.org/aspirecloud/).

## Changelog

### 0.0.1
- Initial release
- Root-level api.wordpress.org API passthrough
- Headless WordPress functionality
- WordPress admin preservation
