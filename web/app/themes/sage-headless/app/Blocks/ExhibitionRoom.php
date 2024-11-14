<?php

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class ExhibitionRoom extends Block
{
    /**
     * The block name.
     *
     * @var string
     */
    public $name = 'Exhibition Room';

    /**
     * The block description.
     *
     * @var string
     */
    public $description = 'A simple Exhibition Room block.';

    /**
     * The block category.
     *
     * @var string
     */
    public $category = 'formatting';

    /**
     * The block icon.
     *
     * @var string|array
     */
    public $icon = 'layout';

    /**
     * The block keywords.
     *
     * @var array
     */
    public $keywords = [];

    /**
     * The block post type allow list.
     *
     * @var array
     */
    public $post_types = [];

    /**
     * The parent block type allow list.
     *
     * @var array
     */
    public $parent = [];

    /**
     * The ancestor block type allow list.
     *
     * @var array
     */
    public $ancestor = [];

    /**
     * The default block mode.
     *
     * @var string
     */
    public $mode = 'preview';

    /**
     * The default block alignment.
     *
     * @var string
     */
    public $align = '';

    /**
     * The default block text alignment.
     *
     * @var string
     */
    public $align_text = '';

    /**
     * The default block content alignment.
     *
     * @var string
     */
    public $align_content = '';

    /**
     * The supported block features.
     *
     * @var array
     */
    public $supports = [
        'align' => ['left', 'center', 'right', 'wide', 'full'],  // specify allowed alignments
        'align_text' => false,
        'align_content' => false,
        'full_height' => false,
        'anchor' => false,
        'mode' => true,
        'multiple' => true,
        'jsx' => true,
        'color' => [
            'background' => true,
            'text' => true,
            'gradient' => true,
        ],
    ];
    public function __construct($composer = null)
    {
        parent::__construct($composer);

        // Register only the reference field
        add_action('graphql_register_types', function () {
            register_graphql_field('MediaItem', 'reference', [
                'type' => 'String',
                'description' => 'Book reference extracted from filename',
                'resolve' => function ($source) {
                    try {
                        $file = get_attached_file($source->databaseId);
                        if (!$file) {
                            return null;
                        }
                        return $this->extractBookReference($file);
                    } catch (\Exception $e) {
                        error_log('Error resolving reference: ' . $e->getMessage());
                        return null;
                    }
                }
            ]);
        });
    }

    /**
     * Add GraphQL resolver for the slug field
     */
    public function addSlugResolver($resolver, $source, $args, $context)
    {
        // For debugging
        error_log('Source type: ' . get_class($source));
        error_log('Source data: ' . print_r($source, true));

        // If this is a MediaItem node
        if ($source instanceof \WPGraphQL\Model\Post && $source->post_type === 'attachment') {
            $file_path = get_attached_file($source->ID);

            if ($file_path) {
                // Extract reference from filename
                $reference = $this->extractBookReference($file_path);

                if ($reference) {
                    // If we're resolving the reference field
                    if ($args['fieldName'] === 'reference') {
                        return $reference;
                    }
                }
            }
        }

        return $resolver;
    }


    /**
     * The block template.
     *
     * @var array
     */
    public $template = [
        'core/heading' => ['placeholder' => 'Hello World'],
        'core/paragraph' => ['placeholder' => 'Welcome to the Exhibition Room block.'],
    ];

    /**
     * Data to be passed to the block before rendering.
     */
    public function with(): array
    {
        return [
            'cabinets' => $this->getCabinets(),
            'name_ar' => get_field('name_ar'),
            'name_en' => get_field('name_en'),
            'intro_text' => get_field('intro_text'),
        ];
    }

    /**
     * The block field group.
     */
    public function fields(): array
    {
        $exhibitionRoom = Builder::make('exhibition_room');

        $exhibitionRoom
            ->addText('name_ar')
            ->addText('name_en')
            ->addWysiwyg('intro_text')

            ->addRepeater('cabinets', [
                'button_label' => 'Add Cabinet',
                'layout' => 'block',
            ])
            ->addText('name_ar')
            ->addText('name_en')
            ->addWysiwyg('intro_text')
            ->addRepeater('groups', [
                'button_label' => 'Add Group',
                'layout' => 'block',
            ])
            ->addSelect('layout', [
                'choices' => [
                    'miniatures' => 'Miniatures',
                    'organic' => 'Organic Layout',
                    'centered' => 'Centered',
                ],
            ])
            ->addGallery('images')
            ->endRepeater()
            ->endRepeater();

        return $exhibitionRoom->build();
    }
    /**
     * Extract book reference from image filename
     */
    private function extractBookReference($filename)
    {
        try {
            // Remove file extension and path
            $filename = basename($filename);

            // Pattern matches: XZZZ, X is letter, ZZZ is 3 digits, optional 'c' suffix
            if (preg_match('/([a-z]\d{3}[a-z]?)/i', $filename, $matches)) {
                return strtoupper($matches[1]);
            }

            return null;
        } catch (\Exception $e) {
            error_log('Error in extractBookReference: ' . $e->getMessage());
            return null;
        }
    }

    public function getCabinets()
    {
        $cabinets = get_field('cabinets') ?: [];

        return array_map(function ($cabinet) {
            $cabinet['groups'] = array_map(function ($group) {
                // Process images in the gallery
                if (!empty($group['images'])) {
                    $group['images'] = array_map(function ($image) {
                        // Extract book reference from filename
                        $reference = $this->extractBookReference($image['filename']);
                        $filename = $image['filename'];

                        // If reference found, get corresponding book
                        if ($reference) {
                            // Query for book post by slug
                            $books = get_posts([
                                'post_type' => 'book',
                                'name' => strtolower($reference),
                                'posts_per_page' => 1
                            ]);

                            $image['book'] = !empty($books) ? $books[0] : null;
                            $image['reference'] = $reference;
                        }

                        return $image;
                    }, $group['images']);
                }
                return $group;
            }, $cabinet['groups'] ?: []);
            return $cabinet;
        }, $cabinets);
    }

    /**
     * Assets enqueued when rendering the block.
     */
    public function assets(array $block): void
    {
        //
    }
}
