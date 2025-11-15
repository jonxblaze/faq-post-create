# FAQ Post Creator

**Contributors:** Jon Blaze  
**Tags:** faq, questions, submissions, custom post type, contact form  
**Requires at least:** 5.0  
**Tested up to:** 6.4  
**Stable tag:** 1.0.2
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

Allows non-logged-in users to submit questions that become draft FAQ posts for admin approval.

## Description

The FAQ Post Creator plugin enables visitors to your site to submit questions through a front-end form. These questions are converted into draft FAQ posts that administrators can review and answer before publishing.

### Features:
* Front-end form for question submission (Full Name, Email, Title, Question)
* Custom post type for FAQs with special single post template
* Draft posts for admin review
* Honeypot field for bot detection
* AJAX form submission
* Admin meta box for separate response entry
* Separate display of original question and admin response
* Publication date display
* Responsive design

## Installation

1. Upload the `faq-post-create` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Use the `[faq_submission_form]` shortcode to display the form on any page or post

## Frequently Asked Questions

### How do I display the FAQ submission form?

Use the shortcode `[faq_submission_form]` on any page or post where you want the form to appear.

### Where do the submitted questions appear?

Submitted questions appear as draft posts in the FAQ section of your WordPress admin dashboard.

### Can I customize the form?

Yes, you can customize the form title by using the `title` attribute: `[faq_submission_form title="Ask Your Question"]`

### How do admins respond to questions?

Admins can respond to questions using the "Admin Response" meta box when editing FAQ posts in the WordPress admin. The original question appears separately from the admin response on the public FAQ page.

### How is the FAQ page structured?

The single FAQ template displays the FAQ title with publication date, the submitter's name, and then the question content, followed by the admin response (when available) in a clean, separated layout.

### Is there protection against spam bots?

Yes, the form includes a honeypot field that helps identify and block automated bot submissions.

## Changelog

### 1.0.2
* Fixed custom post type registration issue.
* Implemented dynamic plugin version retrieval from the main plugin file header.

### 1.0.1
* Added publication date display under the main FAQ title
* Improved template structure by removing redundant headings
* Hide user email address - only display full name
* Changed response header from H2 to H3
* Added date display in admin meta box
* Updated FAQ page structure for better layout

### 1.0
* Initial release
* Added front-end submission form with Full Name, Email, Title, and Question fields
* Added honeypot field for bot protection
* Created custom post type for FAQs
* Implemented special template for FAQ posts
* Added admin meta box for separate response entry
* Added AJAX form submission
* Added rate limiting to prevent spam
* Added draft functionality for admin review
