# Auto ALT Text Generator for WordPress

A WordPress plugin that automatically generates descriptive ALT text for images using Azure Computer Vision AI and supports translation to Finnish.

## Description

This plugin helps improve website accessibility and SEO by automatically generating descriptive ALT text for images using Microsoft Azure's Computer Vision AI. It can analyze images and generate natural-language descriptions in English, with optional translation to Finnish.

### Features

- Automatically generates descriptive ALT text for new image uploads
- Bulk process existing images without ALT text
- Option to regenerate ALT text for all images
- Supports both English and Finnish languages
- Detailed image analysis including:
  - Main scene description
  - Object detection
  - Color analysis
  - Face detection
  - Additional contextual tags
- Real-time processing status with debug console
- Test connection feature for API credentials

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher
- Microsoft Azure account with:
  - Computer Vision API subscription
  - Translator API subscription (if Finnish translation is needed)

## Installation

1. Download the plugin zip file
2. Go to WordPress admin panel → Plugins → Add New
3. Click "Upload Plugin" and select the downloaded zip file
4. Click "Install Now" and then "Activate"

## Configuration

1. In WordPress admin, go to Settings → Auto ALT Text
2. Enter your Azure API credentials:
   - Computer Vision API endpoint
   - Computer Vision API key
   - Translator API key (if using Finnish)
   - Select your Azure region
3. Choose your preferred language (English or Finnish)
4. Click "Test Azure Connection" to verify your settings
5. Save changes

### Getting Azure Credentials

1. Create an Azure account at [portal.azure.com](https://portal.azure.com)
2. Create a Computer Vision resource:
   - Get the endpoint and key from "Keys and Endpoint" section
3. If using Finnish, create a Translator resource:
   - Get the key and note your region

## Usage

### For New Images
- ALT text is automatically generated when you upload new images to WordPress

### For Existing Images
1. Go to Settings → Auto ALT Text
2. Click "Scan Images" to find images without ALT text
3. Use either:
   - "Generate Missing ALT Text" for images without ALT text
   - "Regenerate All ALT Text" to process all images
4. Monitor progress in the debug console

## Rate Limits

- Free tier: 20 requests per minute
- S1 tier: 10 requests per second

The plugin automatically respects these limits to ensure reliable processing.

## Debug Console

The plugin includes a debug console that shows:
- API responses
- Translation status
- Error messages
- Processing progress

Access it by clicking "Show Debug Console" on the settings page.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the GPL v2 or later.

## Credits

Created by Tomi with assistance from AI.

## Support

For issues and feature requests, please use the GitHub issues page.

## Changelog

### 1.0.0
- Initial release
- Basic ALT text generation
- Finnish translation support
- Debug console
- Bulk processing features

Preview: https://streamable.com/pu6qxm
