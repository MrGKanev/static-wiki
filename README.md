# Company Wiki

A simple, fast, and secure file-based wiki system using Markdown files. Perfect for company documentation, team knowledge bases, and internal documentation.

## Features

- **File-based** - No database required, just Markdown files
- **Fast** - Built-in caching system for excellent performance
- **Search** - Full-text search across all content
- **Responsive** - Works on desktop and mobile
- **Secure** - Read-only web interface, content managed via Git
- **Clean Design** - Professional, modern interface
- **Navigation** - Automatic menu generation from folder structure

## Quick Start

### Adding Files

- Copy all PHP files to the correct directories
- Copy CSS to `assets/style.css`
- Create your content in the `content/` directory


## Project Structure

```
company-wiki/
├── README.md                # This file
├── index.php               # Main entry point
├── config.php              # Configuration
├── .htaccess               # Apache config (optional)
├── classes/
│   ├── Cache.php           # Caching system
│   ├── MarkdownParser.php  # Markdown processing
│   └── Wiki.php            # Core functionality
├── templates/
│   ├── layout.php          # Main layout
│   ├── page.php            # Page template
│   └── search.php          # Search results
├── assets/
│   └── style.css           # Styling
├── cache/                  # Cache storage (auto-created)
└── content/                # Your wiki content
    ├── index.md            # Home page
    ├── development/
    │   ├── index.md
    │   └── coding-standards.md
    └── hr/
        └── policies.md
```

### Navigation Structure

- **Folders** become navigation categories
- **Files** become pages within categories
- **index.md** files become category overview pages

Example:

```
content/
├── index.md              → Home page
├── development/          → "Development" category
│   ├── index.md         → Category overview
│   └── standards.md     → "Standards" page
└── hr/                  → "HR" category
    └── policies.md      → "Policies" page
```

## ⚙️ Configuration

### Basic Settings (`config.php`)

```php
define('WIKI_TITLE', 'Your Company Wiki');
define('DEBUG_MODE', false);        // Set true for development
define('ENABLE_CACHE', true);       // Performance caching
```

### Cache Settings

```php
define('NAVIGATION_CACHE_TTL', 7200); // 2 hours
define('CONTENT_CACHE_TTL', 1800);    // 30 minutes
define('SEARCH_CACHE_TTL', 600);      // 10 minutes
```

## 🔧 Deployment

### Development

```bash
php -S localhost:8000
```

### Production (Apache)

1. Upload files to web server
2. Ensure `.htaccess` is in place for clean URLs
3. Set proper permissions:

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

## 🐛 Troubleshooting

### Common Issues

**Pages not loading**

- Check file permissions
- Verify content directory structure
- Ensure .md file extensions

**Search not working**

- Verify content files are readable
- Check for PHP errors in logs
- Test with longer search terms

**Cache issues**

- Check cache directory permissions: `chmod 755 cache/`
- Clear cache: `rm -f cache/*.cache`
- Disable temporarily: Set `ENABLE_CACHE = false`

**Navigation missing**

- Ensure content directory has files
- Check folder permissions
- Verify markdown file structure

### Debug Mode

Set `DEBUG_MODE = true` in config.php to see:

- File paths and errors
- Cache statistics
- Performance information
- Clear cache buttons

## Contributing

1. Fork the project
2. Create feature branch
3. Test thoroughly
4. Submit pull request

## License

This project is open source. Use it freely for your company wiki needs.
