<?php

namespace Tests;

class RemoteCollectionsTest extends TestCase
{
    /**
     * @test
     */
    public function collection_does_not_require_matching_source_directory()
    {
        $config = collect([
            'collections' => [
                'collection_without_directory' => [],
            ],
        ]);
        $siteData = $this->buildSiteData($this->setupSource(), $config);

        $this->assertTrue($siteData->has('collection_without_directory'));
        $this->assertCount(0, $siteData->collection_without_directory);
    }

    /**
     * @test
     */
    public function collection_items_are_created_from_files_in_a_collection_directory()
    {
        $config = collect([
            'collections' => [
                'collection' => [],
            ],
        ]);
        $files = $this->setupSource([
            '_collection' => [
                'file_1.md' => 'Test markdown file #1',
                'file_2.md' => 'Test markdown file #2',
            ],
        ]);
        $siteData = $this->buildSiteData($files, $config);

        $this->assertCount(2, $siteData->collection);
        $this->assertEquals(
            '<p>Test markdown file #1</p>',
            $this->clean($siteData->collection->file_1->getContent()),
        );
        $this->assertEquals(
            '<p>Test markdown file #2</p>',
            $this->clean($siteData->collection->file_2->getContent()),
        );
    }

    /** @test */
    public function collection_items_without_matching_handler_are_ignored()
    {
        $config = collect(['collections' => ['collection' => []]]);
        $files = $this->setupSource([
            '_collection' => [
                '.git' => '-',
                'file.md' => 'Test markdown file',
            ],
        ]);

        $siteData = $this->buildSiteData($files, $config, 3);

        $this->assertTrue($siteData->has('collection'));
        $this->assertCount(1, $siteData->collection);
    }

    /**
     * @test
     */
    public function output_files_are_built_from_files_in_a_collection_directory()
    {
        $config = collect([
            'collections' => [
                'collection' => [],
            ],
        ]);
        $yaml_header = implode("\n", ['---', 'extends: _layouts.master', 'section: content', '---']);
        $files = $this->setupSource([
            '_layouts' => [
                'master.blade.php' => "<div>@yield('content')</div>",
            ],
            '_collection' => [
                'file_1.md' => $yaml_header . 'File 1 Content',
                'file_2.md' => $yaml_header . 'File 2 Content',
            ],
        ]);
        $this->buildSite($files, $config);

        $this->assertCount(2, $files->getChild('build/collection')->getChildren());
        $this->assertEquals(
            '<div><p>File 1 Content</p></div>',
            $this->clean($files->getChild('build/collection/file-1.html')->getContent()),
        );
        $this->assertEquals(
            '<div><p>File 2 Content</p></div>',
            $this->clean($files->getChild('build/collection/file-2.html')->getContent()),
        );
    }

    /**
     * @test
     */
    public function output_files_are_built_from_items_key_in_config()
    {
        $config = collect([
            'collections' => [
                'test' => [
                    'items' => [
                        [
                            'extends' => '_layouts.master',
                            'content' => 'item content',
                        ],
                    ],
                ],
            ],
        ]);
        $files = $this->setupSource([
            '_layouts' => [
                'master.blade.php' => "<div>@yield('content')</div>",
            ],
        ]);
        $this->buildSite($files, $config);

        $this->assertCount(1, $files->getChild('build/test')->getChildren());
        $this->assertEquals(
            '<div><p>item content</p></div>',
            $this->clean($files->getChild('build/test/test-1.html')->getContent()),
        );
    }

    /**
     * @test
     */
    public function output_files_are_built_from_items_key_in_config_and_from_files_in_collection_directory()
    {
        $config = collect([
            'collections' => [
                'test' => [
                    'items' => [
                        [
                            'extends' => '_layouts.master',
                            'content' => 'config content',
                        ],
                    ],
                ],
            ],
        ]);
        $yaml_header = implode("\n", ['---', 'extends: _layouts.master', 'section: content', '---']);

        $files = $this->setupSource([
            '_layouts' => [
                'master.blade.php' => "<div>@yield('content')</div>",
            ],
            '_test' => [
                'file_1.md' => $yaml_header . 'file content',
            ],
        ]);
        $this->buildSite($files, $config);

        $this->assertCount(2, $files->getChild('build/test')->getChildren());
        $this->assertEquals(
            '<div><p>config content</p></div>',
            $this->clean($files->getChild('build/test/test-1.html')->getContent()),
        );
        $this->assertEquals(
            '<div><p>file content</p></div>',
            $this->clean($files->getChild('build/test/file-1.html')->getContent()),
        );
    }

    /**
     * @test
     */
    public function temporary_directory_for_remote_items_is_removed_after_build_is_complete()
    {
        $config = collect([
            'collections' => [
                'test' => [
                    'items' => [
                        [
                            'extends' => '_layouts.master',
                            'content' => 'item content',
                        ],
                    ],
                ],
            ],
        ]);
        $files = $this->setupSource([
            '_layouts' => [
                'master.blade.php' => "<div>@yield('content')</div>",
            ],
        ]);
        $this->buildSite($files, $config);

        $this->assertFileMissing($this->tmpPath('source/_test/_tmp'));
    }

    /**
     * @test
     */
    public function temporary_parent_directory_for_remote_items_is_removed_if_empty_after_build_is_complete()
    {
        $config = collect([
            'collections' => [
                'test' => [
                    'items' => [
                        [
                            'extends' => '_layouts.master',
                            'content' => 'item content',
                        ],
                    ],
                ],
            ],
        ]);
        $files = $this->setupSource([
            '_layouts' => [
                'master.blade.php' => "<div>@yield('content')</div>",
            ],
        ]);
        $this->buildSite($files, $config);

        $this->assertFileMissing($this->tmpPath('source/_test'));
    }

    /**
     * @test
     */
    public function items_key_in_config_can_return_an_illuminate_collection()
    {
        $config = collect([
            'collections' => [
                'test' => [
                    'items' => collect([
                        [
                            'extends' => '_layouts.master',
                            'content' => 'item content',
                        ],
                    ]),
                ],
            ],
        ]);
        $files = $this->setupSource([
            '_layouts' => [
                'master.blade.php' => "<div>@yield('content')</div>",
            ],
        ]);
        $this->buildSite($files, $config);

        $this->assertCount(1, $files->getChild('build/test')->getChildren());
        $this->assertEquals(
            '<div><p>item content</p></div>',
            $this->clean($files->getChild('build/test/test-1.html')->getContent()),
        );
    }

    /**
     * @test
     */
    public function value_of_content_key_in_item_array_is_parsed_as_markdown()
    {
        $config = collect([
            'collections' => [
                'test' => [
                    'items' => [
                        [
                            'extends' => '_layouts.master',
                            'content' => '## item content',
                        ],
                    ],
                ],
            ],
        ]);
        $files = $this->setupSource([
            '_layouts' => [
                'master.blade.php' => "<div>@yield('content')</div>",
            ],
        ]);
        $this->buildSite($files, $config);

        $this->assertEquals(
            '<div><h2>item content</h2></div>',
            $this->clean($files->getChild('build/test/test-1.html')->getContent()),
        );
    }

    /**
     * @test
     */
    public function page_variables_are_created_from_keys_in_item_array()
    {
        $config = collect([
            'collections' => [
                'test' => [
                    'items' => [
                        [
                            'extends' => '_layouts.master',
                            'content' => 'item content',
                            'variable' => 'page variable',
                        ],
                    ],
                ],
            ],
        ]);
        $files = $this->setupSource([
            '_layouts' => [
                'master.blade.php' => '<div>{{ $page->variable }}</div>',
            ],
        ]);
        $this->buildSite($files, $config);

        $this->assertEquals(
            '<div>page variable</div>',
            $this->clean($files->getChild('build/test/test-1.html')->getContent()),
        );
    }

    /**
     * @test
     */
    public function page_variables_are_optional_in_item_array()
    {
        $config = collect([
            'collections' => [
                'test' => [
                    'extends' => '_layouts.master',
                    'items' => [
                        [
                            'content' => 'item content',
                        ],
                    ],
                ],
            ],
        ]);
        $files = $this->setupSource([
            '_layouts' => [
                'master.blade.php' => "<div>@yield('content')</div>",
            ],
        ]);
        $this->buildSite($files, $config);

        $this->assertEquals(
            '<div><p>item content</p></div>',
            $this->clean($files->getChild('build/test/test-1.html')->getContent()),
        );
    }

    /**
     * @test
     */
    public function value_of_string_item_is_parsed_as_markdown_content()
    {
        $config = collect([
            'collections' => [
                'test' => [
                    'extends' => '_layouts.master',
                    'items' => ['## item content'],
                ],
            ],
        ]);
        $files = $this->setupSource([
            '_layouts' => [
                'master.blade.php' => "<div>@yield('content')</div>",
            ],
        ]);
        $this->buildSite($files, $config);

        $this->assertEquals(
            '<div><h2>item content</h2></div>',
            $this->clean($files->getChild('build/test/test-1.html')->getContent()),
        );
    }

    /**
     * @test
     */
    public function strings_and_arrays_can_be_mixed_in_items_key_in_config()
    {
        $config = collect([
            'collections' => [
                'test' => [
                    'extends' => '_layouts.master',
                    'items' => [
                        'item 1 content',
                        [
                            'content' => 'item 2 content',
                            'variable' => 'page variable',
                        ],
                    ],
                ],
            ],
        ]);
        $files = $this->setupSource([
            '_layouts' => [
                'master.blade.php' => "<div>@yield('content')</div>",
            ],
        ]);
        $this->buildSite($files, $config);

        $this->assertEquals(
            '<div><p>item 1 content</p></div>',
            $this->clean($files->getChild('build/test/test-1.html')->getContent()),
        );
        $this->assertEquals(
            '<div><p>item 2 content</p></div>',
            $this->clean($files->getChild('build/test/test-2.html')->getContent()),
        );
    }

    /**
     * @test
     */
    public function filename_for_output_file_is_set_to_collection_name_plus_index_if_not_specified()
    {
        $config = collect([
            'collections' => [
                'test' => [
                    'extends' => '_layouts.master',
                    'items' => [
                        [
                            'content' => 'item 1',
                        ],
                        [
                            'content' => 'item 2',
                        ],
                    ],
                ],
            ],
        ]);
        $files = $this->setupSource([
            '_layouts' => [
                'master.blade.php' => "<div>@yield('content')</div>",
            ],
        ]);
        $this->buildSite($files, $config);

        $this->assertTrue($files->hasChild('build/test/test-1.html'));
        $this->assertTrue($files->hasChild('build/test/test-2.html'));
    }

    /**
     * @test
     */
    public function filename_for_output_file_is_set_to_collection_name_plus_array_key_if_filename_not_specified_and_key_is_string()
    {
        $config = collect([
            'collections' => [
                'test' => [
                    'extends' => '_layouts.master',
                    'items' => [
                        'foo' => [
                            'content' => 'item 1',
                        ],
                        'bar' => [
                            'content' => 'item 2',
                        ],
                    ],
                ],
            ],
        ]);
        $files = $this->setupSource([
            '_layouts' => [
                'master.blade.php' => "<div>@yield('content')</div>",
            ],
        ]);
        $this->buildSite($files, $config);

        $this->assertTrue($files->hasChild('build/test/foo.html'));
        $this->assertTrue($files->hasChild('build/test/bar.html'));
    }

    /**
     * @test
     */
    public function filename_for_output_file_is_set_to_filename_key_if_specified()
    {
        $config = collect([
            'collections' => [
                'test' => [
                    'extends' => '_layouts.master',
                    'items' => [
                        [
                            'content' => 'item 1',
                            'filename' => 'custom_filename',
                        ],
                    ],
                ],
            ],
        ]);
        $files = $this->setupSource([
            '_layouts' => [
                'master.blade.php' => "<div>@yield('content')</div>",
            ],
        ]);
        $this->buildSite($files, $config);

        $this->assertTrue($files->hasChild('build/test/custom-filename.html'));
    }

    /**
     * @test
     */
    public function items_key_in_config_can_be_a_function_that_returns_an_array()
    {
        $config = collect([
            'collections' => [
                'test' => [
                    'extends' => '_layouts.master',
                    'items' => function () {
                        return [
                            ['content' => 'item 1'],
                            ['content' => 'item 2'],
                        ];
                    },
                ],
            ],
        ]);
        $files = $this->setupSource([
            '_layouts' => [
                'master.blade.php' => "<div>@yield('content')</div>",
            ],
        ]);
        $this->buildSite($files, $config);

        $this->assertEquals(
            '<div><p>item 1</p></div>',
            $this->clean($files->getChild('build/test/test-1.html')->getContent()),
        );
        $this->assertEquals(
            '<div><p>item 2</p></div>',
            $this->clean($files->getChild('build/test/test-2.html')->getContent()),
        );
    }

    /**
     * @test
     */
    public function items_function_can_access_other_config_variables()
    {
        $config = collect([
            'collections' => [
                'test' => [
                    'extends' => '_layouts.master',
                    'items' => function ($config) {
                        return [
                            ['content' => $config['remote_url']],
                        ];
                    },
                ],
            ],
            'remote_url' => 'https://example.com/api',
        ]);
        $files = $this->setupSource([
            '_layouts' => [
                'master.blade.php' => "<div>@yield('content')</div>",
            ],
        ]);
        $this->buildSite($files, $config);

        $this->assertEquals(
            '<div><p>https://example.com/api</p></div>',
            $this->clean($files->getChild('build/test/test-1.html')->getContent()),
        );
    }

    /**
     * @test
     */
    public function items_key_in_config_can_be_a_function_that_returns_a_collection()
    {
        $config = collect([
            'collections' => [
                'test' => [
                    'extends' => '_layouts.master',
                    'items' => function () {
                        return collect([
                            ['content' => 'item 1'],
                            ['content' => 'item 2'],
                        ]);
                    },
                ],
            ],
        ]);
        $files = $this->setupSource([
            '_layouts' => [
                'master.blade.php' => "<div>@yield('content')</div>",
            ],
        ]);
        $this->buildSite($files, $config);

        $this->assertEquals(
            '<div><p>item 1</p></div>',
            $this->clean($files->getChild('build/test/test-1.html')->getContent()),
        );
        $this->assertEquals(
            '<div><p>item 2</p></div>',
            $this->clean($files->getChild('build/test/test-2.html')->getContent()),
        );
    }

    /**
     * @test
     */
    public function items_key_in_config_can_fetch_content_from_a_remote_api()
    {
        $config = collect([
            'collections' => [
                'test' => [
                    'extends' => '_layouts.master',
                    'items' => function () {
                        $remote_post = json_decode(file_get_contents('https://jsonplaceholder.typicode.com/posts/1'));

                        return [
                            ['content' => $remote_post->body],
                        ];
                    },
                ],
            ],
        ]);
        $files = $this->setupSource([
            '_layouts' => [
                'master.blade.php' => "<div>@yield('content')</div>",
            ],
        ]);
        $this->buildSite($files, $config);

        $content = json_decode(file_get_contents('https://jsonplaceholder.typicode.com/posts/1'))->body;
        $this->assertEquals(
            $this->clean('<div><p>' . $content . '</p></div>'),
            $this->clean($files->getChild('build/test/test-1.html')->getContent()),
        );
    }

    /**
     * @test
     */
    public function blade_directives_in_remote_content_get_parsed()
    {
        $config = collect([
            'collections' => [
                'test' => [
                    'extends' => '_layouts.master',
                    'items' => ["Hey {{ 'there' }}"],
                ],
            ],
        ]);
        $files = $this->setupSource([
            '_layouts' => [
                'master.blade.php' => "<div>@yield('content')</div>",
            ],
        ]);
        $this->buildSite($files, $config);

        $this->assertEquals(
            '<div><p>Hey there</p></div>',
            $this->clean($files->getChild('build/test/test-1.html')->getContent()),
        );
    }

    /**
     * @test
     */
    public function collections_key_in_config_can_be_a_function_that_returns_a_list_of_collections()
    {
        $config = collect([
            'collections' => fn () => [
                'test' => [
                    'extends' => '_layouts.master',
                    'items' => function () {
                        return collect([
                            ['content' => 'item 1'],
                            ['content' => 'item 2'],
                        ]);
                    },
                ],
            ],
        ]);
        $files = $this->setupSource([
            '_layouts' => [
                'master.blade.php' => "<div>@yield('content')</div>",
            ],
        ]);
        $this->buildSite($files, $config);

        $this->assertEquals(
            '<div><p>item 1</p></div>',
            $this->clean($files->getChild('build/test/test-1.html')->getContent()),
        );
        $this->assertEquals(
            '<div><p>item 2</p></div>',
            $this->clean($files->getChild('build/test/test-2.html')->getContent()),
        );
    }
}
