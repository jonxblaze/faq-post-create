<?php
/**
 * Template for single FAQ posts
 * 
 * @package FAQ_Post_Create
 * @subpackage Template
 */

get_header(); ?>

<div id="primary" class="content-area">
    <main id="main" class="site-main" role="main">
        <?php
        while (have_posts()) : the_post();
        
            // Get the question details submitted by the user
            $full_name = get_post_meta(get_the_ID(), '_faq_full_name', true);
            $original_question = get_the_excerpt() ? get_the_excerpt() : get_post_meta(get_the_ID(), '_faq_original_question', true);
        ?>
        
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <header class="entry-header">
                <h1 class="entry-title"><?php the_title(); ?></h1>
                <p class="faq-date"><?php echo get_the_date(); ?></p>
            </header>
            
            <div class="entry-content">
                <!-- Admin response section with light background -->
                <div class="faq-response-section">
                    <h3><?php _e('Response', 'faq-post-create'); ?></h3>
                    <?php
                    // Use a custom meta field for admin response to ensure separation
                    $admin_response = get_post_meta(get_the_ID(), '_faq_admin_response', true);

                    // Only show admin response if there's content in the custom field
                    if (!empty(trim($admin_response))):
                        $response_content = $admin_response;
                        ?>
                        <div class="faq-admin-response">
                            <?php echo wp_kses(nl2br($response_content), array(
                                'a' => array(
                                    'href' => array(),
                                    'title' => array(),
                                    'target' => array()
                                ),
                                'br' => array(),
                                'em' => array(),
                                'strong' => array(),
                                'p' => array(),
                                'ul' => array(),
                                'ol' => array(),
                                'li' => array(),
                                'h1' => array(),
                                'h2' => array(),
                                'h3' => array(),
                                'h4' => array(),
                                'h5' => array(),
                                'h6' => array(),
                                'blockquote' => array(),
                                'code' => array(),
                                'pre' => array(),
                                'img' => array(
                                    'src' => array(),
                                    'alt' => array(),
                                    'title' => array()
                                )
                            )); ?>
                        </div>
                    <?php else: ?>
                        <p class="no-response-yet">
                            <?php _e('This question is awaiting a response from our team.', 'faq-post-create'); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </article>
        
        <?php
        endwhile;
        ?>
    </main>
</div>

<?php get_footer(); ?>