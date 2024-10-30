=== ImageMeta ===
Contributors: era404
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=JT8N86V6D2SG6
Tags: images, uploads, meta-data, metadata, delete, storage, attachments, embeds, embedded, attached, descriptions, captions, titles, alternate text, paginated, table, directories, uploads folder, organization, compact, storage, counts, obsolete, orphans, orphaned, missing, gallery, galleries
Requires at least: 3.2.1
Tested up to: 5.5.3
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The fastest way to manage your Wordpress images.

== Description ==

ImageMeta helps Wordpress users manage image titles, captions, descriptions and alternate text more efficiently by providing one central paginated table of the uploads directory, editable fields that instantly update the chosen parameter, and a button to update information for all remaining parameters. Each image is grouped by Wordpress-generated sizes with detailed information about how/if the image is attached or embedded. For orphaned images (not attached or embedded to any post or page), users may click a button to delete all iterations of this image to keep the uploads folder tidy and compact. 


**Simple, Straight-Forward Meta Updates**

The uploads directory (and subdirectories) is presented in a paginated table, sortable by image title or upload date.

* Update Title, Caption, Description or Alt values independently, or...
* Update one, and copy across to the other fields.
* Updates are performed on-the-fly and saved instantly, so no lengthy reloads are necessary.
* A quick reference thumbnail is presented in-line to popup individual images for review.
* An edit link is provided beside each image to fine-tune editing, using traditional Wordpress administration or ERA404's CropRefine Wordpress Plugin.
* Missing images are highlighted and an additional delete button is provided for safe/quick file grooming.
* Quick-edit dropdowns are provided to open/edit posts where images are attached or embedded into the post content.
* Delete button appears by orphaned or unused images not attached or embedded in any posts or pages.

**ImageMeta now performs your routine house-keeping for you**

* **Images Not on Disk:** Individual "Delete" buttons are shown by missing images (JPEG, GIF, PNG) that don't have corresponding files in the uploads directory. Upon clicking "Delete," the obsolete/old records will be removed from the Wordpress database. Additionally, ImageMeta details a count of all missing images at the top of each page. For your convenience, you may click this link to delete all missing image records on the page, keeping your database compact and tidy.

* **Images Not Used in Posts or Pages:** An additional delete link is shown to the right, beside the post counts, for images that are neither attached to a post/page (e.g.: featured image) nor embedded in any post/page content. Users who are vigilant about conserving disk use will appreciate this feature, for clearing out images (and their associated Wordpress-generated sizes/iterations) from their uploads directory. This is especially helpful for images that are no longer being used or were previously uploaded but never posted.


== Installation ==

1. Install ImageMeta either via the WordPress.org plugin directory, or by uploading the files to your server (in the `/wp-content/plugins/` directory).
1. Activate the plugin.
1. Access ImageMeta in the Admin > Tools fly-out, and away you go!

== Screenshots ==

1. The ImageMeta table with examples of image meta-data that can be directly edited, or easily copied across all fields and saved with the click of a button. Images that are missing from the WP Uploads directory are shown with a delete button to clear the old records out of the database.
2. Check for orphaned or unused images, and quickly delete them to recover disk space. 

== Frequently Asked Questions ==

= Can I Propose a Feature? =
If you wish. Sure.

== Changelog ==

= 1.1.2 =
* PHP7.+ adjustments for array typechecking, tested with WordPress 5.5.3, per @7am (thank you).

= 1.1.1 =
* PHP7.+ adjustments for array typechecking, tested with WordPress 5.5.3, per @7am (thank you).

= 1.1.0 =
* Adjusted styles to work better with WordPress 5.3.2

= 1.0.8 =
* Fixed an issue with the pager.

= 1.0.7 =
* Cleaner paging.

= 1.0.6 =
* Added image searching (by filename, title, caption, description and alt)

= 1.0.4 =
* Stricter pattern matching so that (e.g.) image1.jpg will match image1-320x200.jpg but won't match image1-1.jpg
* Revised plugin description with new features

= 1.0.3 =
* Added support for attachments by WooCommerce Image Galleries (meta_key='_product_image_gallery')

= 1.0.2 =
* Additional housekeeping controls added for deleting all images on a page that cannot be found in the WP Uploads directory. This usually occurs when images were removed by a plugin or manually, and the WP database wasn't updated afterward.

= 1.0.0 =
* New feature allows you to locate unused or orphaned images, and to easily delete them to recover unnecessarily used disk space
* Added support for legacy embeds (e.g.: <img id='image123'...) and gallery embeds (e.g.: [gallery ids='123',...])
* Added file sizes for deleted images, disk space recovered, and a disk usage note for the entire WP Uploads directory

= 0.5.0 =
* Fixed how jQuery occasionally produced a plugin conflict. Testified/Verified Compatibility with WordPress 4.8

= 0.4.8 =
* Testified/Verified Compatibility with WordPress 4.2.2

= 0.4.7 =
* Updated styles; Compatibility verified for WP4.2+

= 0.4.6 =
* Added donate link ;)

= 0.4.5 =
* Moved ImageMeta to Tools Menu (where it belongs, and not in settings).

= 0.4.4 =
* Style changes to better match the WP3.8 interface.

= 0.4.2 =
* Per user request, ImageMeta now allows updating meta with a blank/empty value.

= 0.4.1 =
* Minor update to provide PayPal donate button for all the considerate (and handsome) individuals that wish to contribute to this and other fine plug-ins by the developer.

= 0.4 =	
* Added quick-edit dropdowns for posts that contain images, either attached or embedded into post content.
  	
= 0.3 =
* More styles updates.
* Subtle update to screenshot captions

= 0.2 =
* Updated styles for tablet/iDevices

= 0.1 =
* Plugin-out only in beta, currently. Standby for official release.