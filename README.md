# Google Maps Distance Matrix Tester Plugin for WordPress

A WordPress plugin that provides a user interface for testing and demonstrating the Google Distance Matrix API integration. This plugin allows you to easily calculate distances between multiple origins and destinations while managing API settings through the WordPress admin interface.

## Features

- Visual interface for distance calculations
- Support for multiple origins and destinations
- Configurable travel modes (driving, walking, bicycling, transit)
- Comprehensive results display including:
    - Distance in kilometers or miles
    - Travel duration
    - Status indicators for each route
- Flexible route options:
    - Avoid tolls, highways, or ferries
    - Multiple language support
    - Metric or imperial units
- Configurable caching system
- Clear cache management
- Multi-language interface support

## Requirements

- PHP 7.4 or later
- WordPress 6.7.1 or later
- Google Maps API key with Distance Matrix API enabled
- Composer for dependencies

## Installation

1. Download or clone this repository
2. Place in your WordPress plugins directory
3. Run `composer install` in the plugin directory
4. Activate the plugin in WordPress
5. Add your Google Maps API key in Google > Distance Matrix settings

## Usage

1. Navigate to Google > Distance Matrix in your WordPress admin panel
2. Enter your Google Maps API key in the settings section
3. Configure caching preferences (optional)
4. Use the distance calculator form to:
    - Enter origin and destination addresses
    - Select travel mode
    - Choose unit system
    - Set route preferences
    - Select display language
5. View comprehensive results in the formatted table

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the GPL-2.0-or-later License.

## Support

- Documentation: https://github.com/arraypress/google-distance-matrix-plugin
- Issue Tracker: https://github.com/arraypress/google-distance-matrix-plugin/issues