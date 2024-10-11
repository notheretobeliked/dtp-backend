<?php

/**
 * Theme setup.
 */

namespace App;

use function Roots\bundle;
use WP_Query; // Ensure WP_Query is properly imported
use function wp_insert_term;  // Ensure WordPress functions are accessed correctly


/**
 * Register the theme assets.
 *
 * @return void
 */
add_action('wp_enqueue_scripts', function () {
    bundle('app')->enqueue();
}, 100);

/**
 * Register the theme assets with the block editor.
 *
 * @return void
 */
add_action('enqueue_block_editor_assets', function () {
    bundle('editor')->enqueue();
}, 100);

/** importer
 * 
 * 
 */

// Hook into WordPress AJAX

// Hook into WordPress AJAX
add_action('wp_ajax_import_images', __NAMESPACE__ . '\\import_images_ajax_handler');
add_action('wp_ajax_nopriv_import_images', __NAMESPACE__ . '\\import_images_ajax_handler');

function import_images_ajax_handler()
{
    if (isset($_POST['image_directory']) && !empty($_POST['image_directory'])) {
        $image_directory = sanitize_text_field($_POST['image_directory']);

        error_log("Image directory received: $image_directory");

        // Run the image import
        batch_import_images($image_directory);

        wp_send_json_success(['message' => 'Image import completed successfully!']);
    } else {
        error_log("Image directory not specified");
        wp_send_json_error(['message' => 'Image directory not specified.']);
    }
}
function batch_import_images($image_directory, $batch_size = 10)
{
    error_log("Starting batch image import...");

    // Fetch posts in batches to avoid timeout
    $paged = 1;
    $total_pages = 1;

    do {
        $args = [
            'post_type' => 'book',
            'posts_per_page' => $batch_size,
            'paged' => $paged,  // Properly paginate the queries
        ];

        $books = new WP_Query($args);

        if ($books->have_posts()) {
            error_log("Importing batch $paged of {$books->max_num_pages}");
            import_images_for_books($image_directory, $books);
        } else {
            error_log("No books found for batch $paged");
            break;  // Stop if there are no more books to process
        }

        $total_pages = $books->max_num_pages;
        $paged++;

        wp_reset_postdata();  // Reset after each batch

    } while ($paged <= $total_pages);

    error_log("Image import completed for all batches.");
}

function import_images_for_books($image_directory, $books)
{
    // Check if the directory exists
    if (!is_dir($image_directory)) {
        error_log("Directory does not exist or is inaccessible: $image_directory");
        return;
    }

    // Check if the directory path has a trailing slash and add it if missing
    $image_directory = rtrim($image_directory, '/') . '/';

    // Process each book in the current batch
    while ($books->have_posts()) {
        $books->the_post();
        $post_id = get_the_ID();
        $ref = get_field('ref', $post_id); // Get the book's reference number

        error_log("Processing post with ID $post_id and ref $ref");

        if (!$ref) {
            error_log("No reference found for post ID $post_id");
            continue;
        }

        $ref = strtolower($ref); // Convert reference number to lowercase for case-insensitive match
        $images = [];

        // Debug: Ensure the directory path is correct
        error_log("Looking for images in directory: $image_directory");

        // Get all images that match the ref in the directory (case-insensitive)
        $ref_images = glob($image_directory . $ref . '*.[Ww][Ee][Bb][Pp]');  // Use case-insensitive matching for file extension

        if (empty($ref_images)) {
            error_log("No images found for ref $ref in directory $image_directory");
            continue;
        }

        error_log("Images found for ref $ref: " . implode(', ', $ref_images));

        $featured_image_path = null;
        foreach ($ref_images as $image) {
            $filename = basename($image);
            if (strtolower($filename) === strtolower($ref . '.webp')) {
                $featured_image_path = $image;
            } else {
                $images[] = $image;
            }
        }

        // Set the featured image and add its ID to the gallery
        $featured_image_id = null;
        if ($featured_image_path) {
            error_log("Setting featured image for post ID $post_id: $featured_image_path");
            $featured_image_id = upload_image_to_media_library($featured_image_path, $post_id);
            if ($featured_image_id) {
                set_post_thumbnail($post_id, $featured_image_id);
                error_log("Featured image set for post ID $post_id");

                // Add featured image as the first image in the gallery (using the ID)
                $images = array_merge([$featured_image_path], $images); // Reinsert as the first item
            } else {
                error_log("Failed to set featured image for post ID $post_id");
            }
        }

        // Set gallery images in ACF
        if (!empty($images)) {
            error_log("Adding gallery images to post ID $post_id");
            sort($images);
            $gallery_image_ids = [];
            foreach ($images as $index => $image) {
                if ($index === 0 && $featured_image_id) {
                    // The first image is already uploaded as the featured image, so use its ID
                    $gallery_image_ids[] = $featured_image_id;
                    error_log("Added featured image to gallery using existing ID: $featured_image_id");
                } else {
                    $image_id = upload_image_to_media_library($image, $post_id);
                    if ($image_id) {
                        $gallery_image_ids[] = $image_id;
                        error_log("Added image to gallery: $image");
                    } else {
                        error_log("Failed to upload image: $image");
                    }
                }
            }

            if (!empty($gallery_image_ids)) {
                update_field('images', $gallery_image_ids, $post_id);
                error_log("Gallery updated for post ID $post_id");
            }
        }

        // Now set the same images for the Arabic version (WPML)
        $arabic_post_id = apply_filters('wpml_object_id', $post_id, 'book', false, 'ar'); // Get the Arabic translation ID
        if ($arabic_post_id) {
            error_log("Found Arabic post with ID $arabic_post_id for post ID $post_id");

            // Set the same featured image for the Arabic post
            if ($featured_image_id) {
                set_post_thumbnail($arabic_post_id, $featured_image_id);
                error_log("Set featured image for Arabic post ID $arabic_post_id");
            }

            // Set the same gallery images for the Arabic post
            if (!empty($gallery_image_ids)) {
                update_field('images', $gallery_image_ids, $arabic_post_id);
                error_log("Gallery updated for Arabic post ID $arabic_post_id");
            }
        } else {
            error_log("No Arabic version found for post ID $post_id");
        }
    }

    wp_reset_postdata();
}


function upload_image_to_media_library($image_path, $post_id)
{
    // Check if the file exists
    if (!file_exists($image_path)) {
        error_log("File does not exist: $image_path");
        return false;
    }

    error_log("Uploading image: $image_path");

    // Upload image to the WordPress media library
    $filetype = wp_check_filetype(basename($image_path), null);
    $upload_file = wp_upload_bits(basename($image_path), null, file_get_contents($image_path));

    if (!$upload_file['error']) {
        $attachment = [
            'guid' => $upload_file['url'],
            'post_mime_type' => $filetype['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($image_path)),
            'post_content' => '',
            'post_status' => 'inherit'
        ];

        // Insert attachment
        $attachment_id = wp_insert_attachment($attachment, $upload_file['file'], $post_id);

        if (!is_wp_error($attachment_id)) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');

            // Generate attachment metadata and update
            $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload_file['file']);
            wp_update_attachment_metadata($attachment_id, $attachment_data);

            error_log("Image uploaded successfully: $image_path (Attachment ID: $attachment_id)");

            return $attachment_id;
        } else {
            error_log("Error inserting attachment: " . $attachment_id->get_error_message());
        }
    } else {
        error_log("Error uploading file: " . $upload_file['error']);
    }

    return false;
}



// Hook into WordPress AJAX
add_action('wp_ajax_import_data', __NAMESPACE__ . '\\import_data');
add_action('wp_ajax_nopriv_import_data', __NAMESPACE__ . '\\import_data');

// Main function to handle CSV import for both books and taxonomies
function import_data()
{
    error_log('Starting import_data function');

    // Check if a file is uploaded and the import type is set
    if (isset($_FILES['csv_file']) && !empty($_FILES['csv_file']['tmp_name']) && isset($_POST['import_type'])) {
        error_log('CSV file uploaded: ' . $_FILES['csv_file']['name']);

        $import_type = sanitize_text_field($_POST['import_type']);  // Get the selected import type
        $csv_file = $_FILES['csv_file']['tmp_name'];

        // Open the file and read the content
        if (($handle = fopen($csv_file, 'r')) !== FALSE) {
            error_log('Opened the CSV file for reading');
            $row = 0;

            if ($import_type === 'books') {
                // Handle Master CSV (Books)
                import_books_from_master_csv($handle);
            } else {
                // Handle taxonomy imports (People, Collection, Publisher)
                import_taxonomy_terms($handle, $import_type);
            }

            fclose($handle);
            error_log('CSV file processing completed');
            wp_send_json_success(['message' => ucfirst($import_type) . ' imported successfully!']);
        } else {
            error_log('Error opening CSV file');
            wp_send_json_error(['message' => 'Error opening file.']);
        }
    } else {
        error_log('No file uploaded or import type missing');
        wp_send_json_error(['message' => 'No file uploaded or import type missing.']);
    }
}

// Function to handle the Master CSV (Books) import
function import_books_from_master_csv($handle)
{
    $row = 0;
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        if ($row == 0) {
            error_log('Skipping header row');
            $row++;
            continue;
        }

        // Import English book post
        $english_post_id = create_book_post($data, 'en');
        if (!$english_post_id) {
            error_log('Error creating English book post.');
            continue;
        }

        // Import Arabic book post and link it to the English post using WPML
        $arabic_post_id = create_book_post($data, 'ar', $english_post_id);
        if (!$arabic_post_id) {
            error_log('Error creating Arabic book post.');
            continue;
        }

        $row++;
    }
}

// Function to handle taxonomy CSVs (People, Collection, Publisher)
function import_taxonomy_terms($handle, $taxonomy)
{
    $row = 0;
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        if ($row == 0) {
            error_log('Skipping header row');
            $row++;
            continue;
        }

        $nameEnglish = $data[0];
        $nameArabic = $data[1];

        // Insert English term
        $english_term = wp_insert_term($nameEnglish, $taxonomy);
        if (is_wp_error($english_term)) {
            error_log('Error creating English term: ' . $english_term->get_error_message());
            continue;
        }

        // Insert Arabic term
        $arabic_term = wp_insert_term($nameArabic, $taxonomy);
        if (is_wp_error($arabic_term)) {
            error_log('Error creating Arabic term: ' . $arabic_term->get_error_message());
            continue;
        }

        // WPML linking using wpml_set_element_language_details hook
        $english_term_id = $english_term['term_id'];
        $arabic_term_id = $arabic_term['term_id'];

        do_action('wpml_set_element_language_details', [
            'element_id' => $english_term_id,
            'element_type' => 'tax_' . $taxonomy,  // Taxonomy type
            'trid' => false,
            'language_code' => 'en',
            'source_language_code' => null
        ]);

        $trid = apply_filters('wpml_element_trid', false, $english_term_id, 'tax_' . $taxonomy);
        do_action('wpml_set_element_language_details', [
            'element_id' => $arabic_term_id,
            'element_type' => 'tax_' . $taxonomy,  // Taxonomy type
            'trid' => $trid,
            'language_code' => 'ar',
            'source_language_code' => 'en'
        ]);

        error_log('Linked English term ID ' . $english_term_id . ' with Arabic term ID ' . $arabic_term_id);

        $row++;
    }
}

function create_book_post($data, $language, $english_post_id = null)
{
    $slug = sanitize_title($data[0]);  // Use the reference number as the slug for both English and Arabic

    $post_data = [
        'post_type' => 'book',
        'post_title' => $data[0],  // ref number as title for both Arabic and English
        'post_status' => 'publish',
        'post_name' => $slug,      // Set the same slug for both posts
    ];

    // Insert the post
    $post_id = wp_insert_post($post_data);
    if (is_wp_error($post_id)) {
        error_log('Error creating post: ' . $post_id->get_error_message());
        return false;
    }

    update_field('title', $language == 'en' ? $data[1] : $data[2], $post_id);  // Ref. A
    update_field('ref', $data[0], $post_id);  //
    update_field('series', $language == 'en' ? $data[5] : $data[6], $post_id);  //
    update_field('place', $language == 'en' ? $data[13] : $data[14], $post_id);  // Place (Column index for place)
    update_field('year', $data[15], $post_id);  // Year (Column index for year)
    update_field('edition', $language == 'en' ? $data[16] : $data[17], $post_id);  // Place (Column index for place)
    update_field('printer', $language == 'en' ? $data[18] : $data[19], $post_id);  // Printer (Column index for printer)
    update_field('pp', $data[32], $post_id);  // Number of Pages (Column index for pages)
    update_field('size', $data[33], $post_id);  // Size (Column index for size)
    update_field('notes', $language == 'en' ? $data[34] : $data[35], $post_id);  // Notes_EN (Column index for notes)
    update_field('exhibition', $data[36], $post_id);  // Notes_EN (Column index for notes)

    // If it's the English post, update ACF fields and taxonomy
    if ($language == 'en') {
        // Map ACF fields correctly

        // Set Taxonomies (People, Collection, Publisher)
        set_book_taxonomies($post_id, $data, 'en');
    } else {
        // For Arabic posts, just set the title and WPML linking
        set_wpml_translation($post_id, $english_post_id, 'ar');
    }

    return $post_id;
}


function set_book_taxonomies($post_id, $data, $language)
{
    // ACF Taxonomy Field: Author (linked to 'people' taxonomy)
    $author_name = $language == 'en' ? $data[7] : $data[8];  // Author_EN or Author_AR
    error_log('Author: ' . $author_name);

    if (!empty($author_name)) {
        set_multiple_acf_taxonomy_terms($post_id, $author_name, 'person_author', 'person');
    }

    // ACF Taxonomy Field: Translator (linked to 'people' taxonomy)
    $translator_name = $language == 'en' ? $data[9] : $data[10];  // Translator_EN or Translator_AR
    error_log('translator: ' . $translator_name);

    if (!empty($translator_name)) {
        set_multiple_acf_taxonomy_terms($post_id, $translator_name, 'person_translation', 'person');
    }

    // ACF Taxonomy Field: Cover Design (linked to 'people' taxonomy)
    $cover_design_name = $language == 'en' ? $data[20] : $data[21];  // Cover design_EN or Cover design_AR
    error_log('Cover design: ' . $cover_design_name);

    if (!empty($cover_design_name)) {
        set_multiple_acf_taxonomy_terms($post_id, $cover_design_name, 'person_cover_design', 'person');
    }

    // ACF Taxonomy Field: Cover Design (linked to 'people' taxonomy)
    $cover_illustration_name = $language == 'en' ? $data[22] : $data[23];  // Cover design_EN or Cover design_AR
    error_log('Cover illustration: ' . $cover_illustration_name);

    if (!empty($cover_illustration_name)) {
        set_multiple_acf_taxonomy_terms($post_id, $cover_illustration_name, 'person_cover_illustration', 'person');
    }

    // ACF Taxonomy Field: Cover Design (linked to 'people' taxonomy)
    $cover_calligraphy_name = $language == 'en' ? $data[28] : $data[29];  // Cover design_EN or Cover design_AR
    error_log('Cover calligraphy: ' . $cover_calligraphy_name);
    if (!empty($cover_calligraphy_name)) {
        set_multiple_acf_taxonomy_terms($post_id, $cover_calligraphy_name, 'person_cover_calligraphy', 'person');
    }


    // ACF Taxonomy Field: Cover Design (linked to 'people' taxonomy)
    $page_design_name = $language == 'en' ? $data[24] : $data[25];  // Cover design_EN or Cover design_AR
    error_log('Page design: ' . $page_design_name);
    if (!empty($page_design_name)) {
        set_multiple_acf_taxonomy_terms($post_id, $page_design_name, 'person_page_design', 'person');
    }


    // ACF Taxonomy Field: Cover Design (linked to 'people' taxonomy)
    $page_calligraphy_name = $language == 'en' ? $data[30] : $data[31];  // Cover design_EN or Cover design_AR
    error_log('Page Calligraphy: ' . $page_calligraphy_name);
    if (!empty($page_calligraphy_name)) {
        set_multiple_acf_taxonomy_terms($post_id, $page_calligraphy_name, 'person_page_calligraphy', 'person');
    }


    // ACF Taxonomy Field: Cover Design (linked to 'people' taxonomy)
    $page_illustration_name = $language == 'en' ? $data[26] : $data[27];  // Cover design_EN or Cover design_AR
    error_log('Page illustration: ' . $page_illustration_name);
    if (!empty($page_illustration_name)) {
        set_multiple_acf_taxonomy_terms($post_id, $page_illustration_name, 'person_page_illustration', 'person');
    }



    // ACF Taxonomy Field: Cover Design (linked to 'people' taxonomy)
    $page_illustration_name = $language == 'en' ? $data[26] : $data[27];  // Cover design_EN or Cover design_AR
    if (!empty($page_illustration_name)) {
        set_multiple_acf_taxonomy_terms($post_id, $page_illustration_name, 'person_page_illustration', 'person');
    }



    // ACF Taxonomy Field: Cover Design (linked to 'people' taxonomy)
    $collection_name = $language == 'en' ? $data[3] : $data[4];  // Cover design_EN or Cover design_AR
    if (!empty($collection_name)) {
        set_multiple_acf_taxonomy_terms($post_id, $collection_name, 'collection', 'collection');
    }



    // ACF Taxonomy Field: Cover Design (linked to 'people' taxonomy)
    $publisher_name = $language == 'en' ? $data[11] : $data[12];  // Cover design_EN or Cover design_AR
    if (!empty($publisher_name)) {
        set_multiple_acf_taxonomy_terms($post_id, $publisher_name, 'publisher', 'publisher');
    }

    // Add other fields like Cover Illustration, Page Design, etc., as needed.
}

function set_multiple_acf_taxonomy_terms($post_id, $taxonomy_names, $acf_field, $taxonomy)
{
    // Split the names by semicolon, trim spaces
    $names = array_map('trim', explode(';', $taxonomy_names));
    error_log("names: ");
    error_log(print_r($names), true);

    // Store term IDs to set for the ACF field
    $term_ids = [];

    foreach ($names as $name) {
        // Check if the term exists in the 'person' taxonomy
        $term = get_term_by('name', $name, $taxonomy);  // Use $taxonomy taxonomy
        error_log('Terms: ');
        error_log(print_r($term, true));

        // If the term doesn't exist, create it
        if (!$term) {
            $term = wp_insert_term($name, $taxonomy);  // Insert into $taxonomy taxonomy
            if (is_wp_error($term)) {
                // Log the error but continue with the next term
                error_log('Error creating term ' . $name . ': ' . $term->get_error_message());
                continue;
            }
            // Access the term ID from wp_insert_term
            $term_ids[] = $term['term_id'];
        } else {
            // If the term exists, just get the term ID from the WP_Term object
            $term_ids[] = $term->term_id;
        }
    }

    // Set the terms for the ACF taxonomy field
    if (!empty($term_ids)) {
        update_field($acf_field, $term_ids, $post_id);
    }
}


function set_wpml_translation($post_id, $english_post_id, $language)
{
    // Link Arabic post as a translation of the English post using WPML
    do_action('wpml_set_element_language_details', [
        'element_id'    => $post_id,
        'element_type'  => 'post_book',  // Custom post type 'book'
        'trid'          => false,        // Create a new trid if none exists
        'language_code' => $language,
        'source_language_code' => null,  // Original post (English) has no source language
    ]);

    // Get the trid for the English post
    $trid = apply_filters('wpml_element_trid', false, $english_post_id, 'post_book');

    // Update the Arabic post to be linked as a translation of the English post
    do_action('wpml_set_element_language_details', [
        'element_id'    => $post_id,
        'element_type'  => 'post_book',
        'trid'          => $trid,
        'language_code' => $language,
        'source_language_code' => 'en',
    ]);

    error_log('Linked Arabic post ID ' . $post_id . ' as translation of English post ID ' . $english_post_id);
}


/**
 * Register the initial theme setup.
 *
 * @return void
 */
add_action('after_setup_theme', function () {
    /**
     * Disable full-site editing support.
     *
     * @link https://wptavern.com/gutenberg-10-5-embeds-pdfs-adds-verse-block-color-options-and-introduces-new-patterns
     */
    remove_theme_support('block-templates');

    /**
     * Register the navigation menus.
     *
     * @link https://developer.wordpress.org/reference/functions/register_nav_menus/
     */
    register_nav_menus([
        'primary_navigation' => __('Primary Navigation', 'sage'),
    ]);

    /**
     * 
     * Set dfefault image sizes
     */

    update_option('thumbnail_size_w', '');
    update_option('thumbnail_size_h', 200);
    update_option('thumbnail_crop', 0);

    update_option('medium_size_w', '');
    update_option('medium_size_h', 400);
    update_option('medium_crop', 0);


    update_option('medium_large_size_w', '');
    update_option('medium_large_size_h', 800);
    update_option('medium_large_crop', 0);

    update_option('large_size_w', '');
    update_option('large_size_h', 1400);
    update_option('large_crop', 0);

    add_filter('intermediate_image_sizes', function ($sizes) {
        return array_diff($sizes, ['1536x1536', '2048x2048']);
    });

    add_action('init', function () {
        remove_image_size('1536x1536');
        remove_image_size('2048x2048');
    });

    add_filter('big_image_size_threshold', '__return_false');

    add_action('init', function() {
        add_image_size('x_large', 0, 2000, 0); // 9999 means unlimited width
    });



    /**
     * Disable the default block patterns.
     *
     * @link https://developer.wordpress.org/block-editor/developers/themes/theme-support/#disabling-the-default-block-patterns
     */
    remove_theme_support('core-block-patterns');

    /**
     * Enable plugins to manage the document title.
     *
     * @link https://developer.wordpress.org/reference/functions/add_theme_support/#title-tag
     */
    add_theme_support('title-tag');

    /**
     * Enable post thumbnail support.
     *
     * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
     */
    add_theme_support('post-thumbnails');

    /**
     * Enable responsive embed support.
     *
     * @link https://developer.wordpress.org/block-editor/how-to-guides/themes/theme-support/#responsive-embedded-content
     */
    add_theme_support('responsive-embeds');

    /**
     * Enable HTML5 markup support.
     *
     * @link https://developer.wordpress.org/reference/functions/add_theme_support/#html5
     */
    add_theme_support('html5', [
        'caption',
        'comment-form',
        'comment-list',
        'gallery',
        'search-form',
        'script',
        'style',
    ]);

    /**
     * Enable selective refresh for widgets in customizer.
     *
     * @link https://developer.wordpress.org/reference/functions/add_theme_support/#customize-selective-refresh-widgets
     */
    add_theme_support('customize-selective-refresh-widgets');
}, 20);

/**
 * Register the theme sidebars.
 *
 * @return void
 */
add_action('widgets_init', function () {
    $config = [
        'before_widget' => '<section class="widget %1$s %2$s">',
        'after_widget' => '</section>',
        'before_title' => '<h3>',
        'after_title' => '</h3>',
    ];

    register_sidebar([
        'name' => __('Primary', 'sage'),
        'id' => 'sidebar-primary',
    ] + $config);

    register_sidebar([
        'name' => __('Footer', 'sage'),
        'id' => 'sidebar-footer',
    ] + $config);
});
