# Company Wiki

A file-based wiki system using Markdown files with automatic navigation, full-text search, caching, and PDF export capabilities.

## Features

- **File-based** - No database required, just Markdown files
- **Fast** - Built-in caching system for excellent performance
- **Search** - Full-text search across all content
- **Responsive** - Works on desktop and mobile
- **Secure** - Read-only web interface, content managed via Git
- **Clean Design** - Professional, modern interface
- **Navigation** - Automatic menu generation from folder structure

## Requirements

- PHP 8.3 or higher
- Composer

## Quick Start

### Local Development

1. Clone the repository
2. Install dependencies:

   ```bash
   composer install
   ```

3. Start development server:

   ```bash
   php -S localhost:8000
   ```

4. Visit <http://localhost:8000>

### Docker Deployment

```bash
docker-compose up -d
```

The application will be available at <http://localhost:8080>

## Project Structure

```
company-wiki/
├── index.php               # Main entry point
├── config.php              # Configuration
├── composer.json           # Dependencies
├── Dockerfile              # Container definition
├── docker-compose.yml      # Docker setup
├── classes/
│   ├── Cache.php           # Caching system
│   ├── MarkdownParser.php  # Markdown processing
│   ├── PDFExporter.php     # PDF export functionality
│   └── Wiki.php            # Core wiki functionality
├── templates/
│   ├── layout.php          # Main page layout
│   ├── page.php            # Page content template
│   └── search.php          # Search results template
├── assets/
│   ├── style.css           # Styling
│   └── keyboard-shortcuts.js # Keyboard navigation
├── cache/                  # Cache storage (auto-created)
└── content/                # Wiki content
    ├── index.md            # Home page
    ├── development/
    │   └── index.md
    └── hr/
        └── onboarding.md
```

## Content Organization

- **Folders** become navigation categories
- **Files** become pages within categories  
- **index.md** files serve as category overview pages

Example structure:

```
content/
├── index.md              # Home page
├── development/          # "Development" category
│   ├── index.md         # Category overview
│   └── standards.md     # "Standards" page
└── hr/                  # "HR" category
    └── policies.md      # "Policies" page
```

## Configuration

Key settings in `config.php`:

```php
define('WIKI_TITLE', 'Your Company Wiki');
define('DEBUG_MODE', false);        // Enable for development
define('ENABLE_CACHE', true);       // Performance caching
define('CACHE_TTL', 3600);          // Cache lifetime in seconds
```

## Keyboard Shortcuts

- `/` - Focus search
- `g h` - Go to home
- `j` / `k` - Navigate between links
- `Enter` - Follow highlighted link
- `p` - Print page
- `e` - Export page to PDF
- `E` - Export section to PDF
- `t` - Toggle table of contents
- `?` - Show help

## Cache Management

The caching system improves performance by storing:

- Parsed markdown content
- Navigation trees
- Search results

Cache can be managed via:

- Automatic cleanup (5% chance per request)
- Manual clearing (debug mode only)
- Configurable TTL per content type

## Deployment

### Production (Apache)

1. Upload files to web server
2. Set permissions:

   ```bash
   chmod 644 *.php classes/*.php templates/*.php
   chmod 755 cache/ content/
   ```

### Production (Nginx)

Add to server configuration:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

## Development

### Debug Mode

Set `DEBUG_MODE = true` in config.php to enable:

- Detailed error reporting
- Cache statistics
- Performance information
- Cache management buttons

### Testing

Run tests with PHPUnit:

```bash
composer test
```

### Clear Cache

```bash
composer run clear-cache
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

MIT License - see LICENSE file for details.
