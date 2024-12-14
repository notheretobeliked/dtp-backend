<?php

// Temporarily disable any autoloaders that might interfere
if (function_exists('spl_autoload_unregister')) {
    foreach (spl_autoload_functions() as $function) {
        spl_autoload_unregister($function);
    }
}
// Load the WordPress environment
require_once __DIR__ . '/wp/wp-load.php';

// Load admin functions for media handling
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

// Path to the file containing filenames
$file = __DIR__ . '/app//uploads/newimages.txt';

if (!file_exists($file)) {
    die("Error: newimages.txt file not found.\n");
}

// Read the file containing image filenames
$filenames = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

if (empty($filenames)) {
    die("Error: No filenames found in newimages.txt.\n");
}

foreach ($filenames as $filename) {
    // Locate the attachment by filename
    global $wpdb;
    $attachment_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND guid LIKE %s",
            '%' . $wpdb->esc_like($filename)
        )
    );

    if (!$attachment_id) {
        echo "Attachment not found for filename: $filename\n";
        continue;
    }

    // Get the full file path for the attachment
    $file_path = get_attached_file($attachment_id);

    if (!file_exists($file_path)) {
        echo "File does not exist for attachment ID: $attachment_id\n";
        continue;
    }

    // Regenerate the thumbnails
    $metadata = wp_generate_attachment_metadata($attachment_id, $file_path);
    if (is_wp_error($metadata)) {
        echo "Error regenerating metadata for attachment ID: $attachment_id\n";
        continue;
    }

    wp_update_attachment_metadata($attachment_id, $metadata);
    echo "Thumbnails regenerated for: $filename (Attachment ID: $attachment_id)\n";
}

echo "Thumbnail regeneration complete.\n";