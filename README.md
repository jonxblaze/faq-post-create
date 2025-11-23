# FAQ Post Creator

**Contributors:** Jon Blaze  
**Tags:** faq, questions, submissions, custom post type, contact form  
**Requires at least:** 5.0  
**Tested up to:** 6.4  
**Stable tag:** 1.0.6
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

Allows non-logged-in users to submit questions that become draft FAQ posts for admin approval.

## Description

The FAQ Post Creator plugin enables visitors to your site to submit questions through a front-end form. These questions are converted into draft FAQ posts that administrators can review and answer before publishing.

### Features:
* Front-end form for question submission (Full Name, Email, Question)
* Custom post type "Questions Answered" with special single post template
* Draft posts for admin review
* Honeypot field for bot detection
* AJAX form submission
* Admin meta box for separate response entry
* Response display on public FAQ page
* Publication date display
* Responsive design
* CSV import functionality for migrating existing FAQs
* **Admin-only question creation** - Questions can only be created via frontend form
* **Clean admin interface** - Simplified "Questions" menu without "Add New" option
* **No archive index** - Individual question pages only, no bulk listing
* **Email notifications** - Automatic emails to submitters when questions are answered
* **reCAPTCHA integration** - Optional spam protection
* **Separated shortcodes** - `[FAQ_FORM]` for form, `[FAQ_LIST]` for paginated list

## Installation

1. Upload the `faq-post-create` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Use the `[FAQ_FORM]` shortcode to display the submission form
4. Use the `[FAQ_LIST]` shortcode to display the list of published FAQs

## Frequently Asked Questions

### How do I display the FAQ submission form?

Use the shortcode `[FAQ_FORM]` on any page or post where you want the form to appear.

### How do I display the list of published FAQs?

Use the shortcode `[FAQ_LIST]` on any page or post where you want the FAQ list to appear.

### Where do the submitted questions appear?

Submitted questions appear as draft posts in the FAQ section of your WordPress admin dashboard.

### Can I customize the form?

Yes, you can customize the form title by using the `title` attribute: `[FAQ_FORM title="Ask Your Question"]`

### Can I customize the FAQ list?

Yes, you can customize the FAQ list title and number of items per page:
- `[FAQ_LIST title="Frequently Asked Questions" posts_per_page="10"]`
- `[FAQ_LIST title="Our Help Center" posts_per_page="50"]`

### How do admins respond to questions?

Admins can respond to questions using the "Admin Response" meta box when editing FAQ posts in the WordPress admin. The original question appears separately from the admin response on the public FAQ page.

### How is the FAQ page structured?

The single FAQ template displays the FAQ title with publication date, followed by the admin response (when available) in a clean, separated layout.

### Is there protection against spam bots?

Yes, the form includes a honeypot field that helps identify and block automated bot submissions.

### How do I import existing FAQs from a CSV file?

The plugin includes a CSV import feature to migrate existing FAQs. Go to FAQ > Import CSV in your WordPress admin. The CSV file should contain the following columns:

* `created` - Date the FAQ was created (will be used as the post date)
* `post_title` - The title of the FAQ
* `post_content` - The answer/response for the FAQ
* `_faq_email` - (Optional) The email associated with the FAQ

All columns except `_faq_email` are required. The import process will map these fields to the appropriate custom post type fields and meta fields used by the plugin.

After importing FAQs or fixing long slugs, you may need to refresh your permalink structure. Go to Settings > Permalinks in your WordPress admin and click "Save Changes" to ensure all FAQ URLs work correctly.

### Why can't I create new questions from the WordPress admin?

The plugin is designed to only accept questions through the frontend submission form. This ensures that all questions come from actual users with proper contact information (name and email). Admins can only edit, respond to, and publish existing questions.

### What happens if someone tries to access the questions archive page?

The archive index for questions has been disabled. If someone tries to access `/questions-answered/`, they will receive a 404 error. Questions are only accessible individually or through the paginated list provided by the `[FAQ_LIST]` shortcode.

### How do email notifications work?

When a new question is submitted, the site admin receives an email notification. When an admin responds to a question and publishes it, the original submitter automatically receives an email with the response and a link to view their answered question.

### Can I change the admin menu name?

The admin menu appears as "Questions" for simplicity. All other labels in the admin interface use the full "Question Answered" terminology for clarity. This cannot be customized without modifying the plugin code.

### Where are the plugin settings located?

Plugin settings are available under "FAQ Settings" in the WordPress admin sidebar. Here you can configure reCAPTCHA settings, notification emails, and access the CSV import tool.

## Changelog

### 1.0.6
* **Code Quality Assurance**: Comprehensive verification of all changes to ensure no code breakages
* **Post Type Consistency**: Updated all references from 'faq' to 'questions-answered' post type
* **Version Management**: Updated plugin version to 1.0.6 across all files
* **Documentation**: Updated README with latest version and changelog

### 1.0.5
* Added FAQ Settings as top-level menu in WordPress admin sidebar
* Moved Import CSV functionality to FAQ Settings submenu
* Added automatic email notifications to submitters when their questions are answered
* Enhanced email system compatibility with WP Mail SMTP
* Improved admin interface organization
* Added settings link to plugin action links for easy access
* **Separated shortcodes**: `[FAQ_FORM]` for submission form only, `[FAQ_LIST]` for FAQ list only

### 1.0.4
* Added email notifications to admin when new FAQs are submitted
* Improved email formatting with HTML support for WP Mail SMTP compatibility
* Enhanced email delivery with proper headers and from address

### 1.0.3
* Added Google reCAPTCHA integration with settings page
* Implemented minimum 5-word requirement for questions
* Added loader with 3-second minimum display during form submission
* Left-aligned submit button
* Added Font Awesome CDN integration
* Added settings page for reCAPTCHA configuration
* Improved form validation and user feedback
* Added success message that replaces form after submission
* Enhanced security with comprehensive input sanitization
* Added smooth scrolling to FAQ list on pagination
* Updated FAQ list to order by post date (newest first)
* Increased FAQ title display to 30 words in list
* Moved FAQ list outside form container with separate styling
* Added proper line break preservation in admin responses
* Added AJAX pagination for FAQ list with 25 items per page
* Added FAQ slug limiting to 10 words maximum
* Added CSV import functionality for existing FAQs
* Removed original submission display from single FAQ view
* Removed title field from submission form - now uses question as title
* Added FAQ list display to submission form shortcode

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
