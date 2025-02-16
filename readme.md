# Curated Posts 
**Contributors:** banago  
**Tags:** curated, editor, option, posts, pages, content  
**Requires at least:** 5.0  
**Tested up to:** 6.7.2  
**Stable tag:** 2.0.0
**License:** GPLv2 or later 
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html  

Create curated lists of posts that you can show anywhere on your site.


## Description 

Curated posts plugin allows you to create unlimited lists of posts that you can show on different section of your website 
using the respective shortcode or directly in your theme by using the PHP function to get the IDs. 


### Features 
* Create unlimited lists of curated posts
* Display on any post using the provided shortcode or PHP function
* Drag and drop posts to reorder them within a list


### Get Involved 

Developers can contribute via the [Curated Posts GitHub Repository](https://github.com/banago/curated-posts).


## Installation 


### Minimum Requirements 
* WordPress 6.2 or greater
* PHP version 7.4 or greater
* MySQL version 7.0 or greater


### Automatic Installation 

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don’t even need to leave your web browser. To do an automatic install of Curated Posts, log in to your WordPress admin panel, navigate to the Plugins menu and click Add New.

In the search field type "Curated Posts" and click Search Plugins. Once you’ve found the plugin you can view details about it such as the point release, rating and description. Most importantly of course, you can install it by simply clicking Install Now. After clicking that link you will be asked if you’re sure you want to install the plugin. Click yes and WordPress will automatically complete the installation.


### Manual Installation 

The manual installation method involves downloading the plugin and uploading it to your webserver via your favorite FTP application.

1. Download the plugin file to your computer and unzip it
2. Using an FTP program, or your hosting control panel, upload the unzipped plugin folder to your WordPress installation’s wp-content/plugins/ directory.
3. Activate the plugin from the Plugins menu within the WordPress admin.


### Upgrading 

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.


## Screenshots 

### 1. Manage lists in admin.
[missing image]

### 2. Posts in the list can be reordered via drag and drop.
[missing image]


## Changelog 

### 2.0.0
* Major rewrite of the admin interface using React
* Replaced jQuery drag and drop with modern @dnd-kit library
* Enhanced search interface with WordPress ComboboxControl component
* Improved accessibility with keyboard navigation and ARIA labels
* Added loading states and error handling
* Better REST API integration for post searching
* Modern UI improvements and styling updates

### 1.1.1
* Rewrite in OOP-style
* Fix a PHP notice
* Added Github action to deploy to WP.org

### 1.0 
* First release.
