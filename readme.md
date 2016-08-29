# VPM User Taxonomies

VPM User Taxonomies is a fork of Peter Shaw's [LH User Taxonomies](https://github.com/shawfactor/lh-user-taxonomies), which is itself a fork of a plugin by Damian Gostomskis, and was also inspired by [work by Justin Tadlock](http://justintadlock.com/archives/2011/10/20/custom-user-taxonomies-in-wordpress).

VPM User Taxonomies aims to provide a clean, minimal implementation of user taxonomies, along with an interface for managing user taxonomies inside the WordPress admin.

Specifically, this implementation of taxonomies aims to achieve feature and experience-parity between user taxonomies and traditional post taxonomies.

This plugin should be considered unstable, and is under active development.

Pull requests are welcome!

## Usage

When calling `register_taxonomy`, you can use `user` as an object type for your taxonomy (either as a string, or as part of your object type array).

You can view and edit the taxonomies associated with a user in the profile edit screen.

You can query a user, based on taxonomies and terms, by adding the `tax_query` parameter to your `WP_User_Query` call.

## Changelog

### 2.0.0
+ Initial version as VPM User Taxonomies
+ Switched to SemVer
+ Handle array-based post type definitions
+ Lots of code formatting improvements
+ Remove support for single_value (not native to WP)
+ Temporarily (we hope) removed support for admin columns and bulk editing, in the interest of refactoring
+ Add support for `tax_query` within `WP_User_Query`

### 1.51
+ Fixed readme

### 1.50
+ Added bulk updates

### 1.41
+ Fix for saving taxonomies on profile when you ned to remove term - thanks Greumb

### 1.4
+ Minor fix, and tree logic for hierarchical taxonomies - thanks again Carl-Gerhard Lindesvard

### 1.3.1
+ Minor fix, thanks lindesvard

### 1.3
+ Better readme.txt

### 1.2
+ Added various patches, props nikolaynesov

### 1.1
+ Added icon

### 1.0
+ Initial Release

## License

This plugin is licensed under the terms of the [GPL v2](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html).
