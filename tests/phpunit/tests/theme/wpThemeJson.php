<?php

/**
 * Test WP_Theme_JSON class.
 *
 * @package WordPress
 * @subpackage Theme
 *
 * @since 5.8.0
 *
 * @group themes
 */

class Tests_Theme_wpThemeJson extends WP_UnitTestCase {

	/**
	 * @ticket 52991
	 */
	public function test_get_settings() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'color'       => array(
						'custom' => false,
					),
					'invalid/key' => 'value',
					'blocks'      => array(
						'core/group' => array(
							'color'       => array(
								'custom' => false,
							),
							'invalid/key' => 'value',
						),
					),
				),
				'styles'   => array(
					'elements' => array(
						'link' => array(
							'color' => array(
								'text' => '#111',
							),
						),
					),
				),
			)
		);

		$actual = $theme_json->get_settings();

		$expected = array(
		);

		$this->assertSameSetsWithIndex( $expected, $actual );
	}

	/**
	 * @ticket 53397
	 */
	public function test_get_settings_presets_are_keyed_by_origin() {
		$core_origin = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'color'       => array(
						'palette' => array(
							array(
								'slug'  => 'white',
								'color' => 'white',
							),
						),
					),
					'invalid/key' => 'value',
					'blocks'      => array(
						'core/group' => array(
							'color' => array(
								'palette' => array(
									array(
										'slug'  => 'white',
										'color' => 'white',
									),
								),
							),
						),
					),
				),
			),
			'core'
		);
		$no_origin   = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'color'       => array(
						'palette' => array(
							array(
								'slug'  => 'black',
								'color' => 'black',
							),
						),
					),
					'invalid/key' => 'value',
					'blocks'      => array(
						'core/group' => array(
							'color' => array(
								'palette' => array(
									array(
										'slug'  => 'black',
										'color' => 'black',
									),
								),
							),
						),
					),
				),
			)
		);

		$actual_core      = $core_origin->get_raw_data();
		$actual_no_origin = $no_origin->get_raw_data();

		$expected_core      = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
		);
		$expected_no_origin = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
		);

		$this->assertEqualSetsWithIndex( $expected_core, $actual_core );
		$this->assertEqualSetsWithIndex( $expected_no_origin, $actual_no_origin );
	}

	/**
	 * @ticket 52991
	 */
	public function test_merge_incoming_data() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'color'  => array(
						'custom'  => false,
						'palette' => array(
							array(
								'slug'  => 'red',
								'color' => 'red',
							),
							array(
								'slug'  => 'green',
								'color' => 'green',
							),
						),
					),
					'blocks' => array(
						'core/paragraph' => array(
							'color' => array(
								'custom' => false,
							),
						),
					),
				),
				'styles'   => array(
					'typography' => array(
						'fontSize' => '12',
					),
				),
			)
		);

		$add_new_block = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
			'settings' => array(
				'blocks' => array(
					'core/list' => array(
						'color' => array(
							'custom' => false,
						),
					),
				),
			),
			'styles'   => array(
				'blocks' => array(
					'core/list' => array(
						'typography' => array(
							'fontSize' => '12',
						),
						'color'      => array(
							'background' => 'brown',
						),
					),
				),
			),
		);

		$add_key_in_settings = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
			'settings' => array(
				'color' => array(
					'customGradient' => true,
				),
			),
		);

		$update_key_in_settings = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
			'settings' => array(
				'color' => array(
					'custom' => true,
				),
			),
		);

		$add_styles = array(
			'version' => WP_Theme_JSON::LATEST_SCHEMA,
			'styles'  => array(
				'blocks' => array(
					'core/group' => array(
						'spacing' => array(
							'padding' => array(
								'top' => '12px',
							),
						),
					),
				),
			),
		);

		$add_key_in_styles = array(
			'version' => WP_Theme_JSON::LATEST_SCHEMA,
			'styles'  => array(
				'blocks' => array(
					'core/group' => array(
						'spacing' => array(
							'padding' => array(
								'bottom' => '12px',
							),
						),
					),
				),
			),
		);

		$add_invalid_context = array(
			'version' => WP_Theme_JSON::LATEST_SCHEMA,
			'styles'  => array(
				'blocks' => array(
					'core/para' => array(
						'typography' => array(
							'lineHeight' => '12',
						),
					),
				),
			),
		);

		$update_presets = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
			'settings' => array(
				'color'      => array(
					'palette'   => array(
						array(
							'slug'  => 'blue',
							'color' => 'blue',
						),
					),
					'gradients' => array(
						array(
							'slug'     => 'gradient',
							'gradient' => 'gradient',
						),
					),
				),
				'typography' => array(
					'fontSizes'    => array(
						array(
							'slug' => 'fontSize',
							'size' => 'fontSize',
						),
					),
					'fontFamilies' => array(
						array(
							'slug'       => 'fontFamily',
							'fontFamily' => 'fontFamily',
						),
					),
				),
			),
		);

		$expected = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
		);

		$theme_json->merge( new WP_Theme_JSON( $add_new_block ) );
		$theme_json->merge( new WP_Theme_JSON( $add_key_in_settings ) );
		$theme_json->merge( new WP_Theme_JSON( $update_key_in_settings ) );
		$theme_json->merge( new WP_Theme_JSON( $add_styles ) );
		$theme_json->merge( new WP_Theme_JSON( $add_key_in_styles ) );
		$theme_json->merge( new WP_Theme_JSON( $add_invalid_context ) );
		$theme_json->merge( new WP_Theme_JSON( $update_presets ) );
		$actual = $theme_json->get_raw_data();

		$this->assertEqualSetsWithIndex( $expected, $actual );
	}

	/**
	 * @ticket 52991
	 */
	public function test_get_from_editor_settings() {
		$input = array(
			'disableCustomColors'    => true,
			'disableCustomGradients' => true,
			'disableCustomFontSizes' => true,
			'enableCustomLineHeight' => true,
			'enableCustomUnits'      => true,
			'colors'                 => array(
				array(
					'slug'  => 'color-slug',
					'name'  => 'Color Name',
					'color' => 'colorvalue',
				),
			),
			'gradients'              => array(
				array(
					'slug'     => 'gradient-slug',
					'name'     => 'Gradient Name',
					'gradient' => 'gradientvalue',
				),
			),
			'fontSizes'              => array(
				array(
					'slug' => 'size-slug',
					'name' => 'Size Name',
					'size' => 'sizevalue',
				),
			),
		);

		$expected = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
			'settings' => array(
				'color'      => array(
					'custom'         => false,
					'customGradient' => false,
					'gradients'      => array(
						array(
							'slug'     => 'gradient-slug',
							'name'     => 'Gradient Name',
							'gradient' => 'gradientvalue',
						),
					),
					'palette'        => array(
						array(
							'slug'  => 'color-slug',
							'name'  => 'Color Name',
							'color' => 'colorvalue',
						),
					),
				),
				'spacing'    => array(
					'units' => array( 'px', 'em', 'rem', 'vh', 'vw' ),
				),
				'typography' => array(
					'customFontSize'   => false,
					'customLineHeight' => true,
					'fontSizes'        => array(
						array(
							'slug' => 'size-slug',
							'name' => 'Size Name',
							'size' => 'sizevalue',
						),
					),
				),
			),
		);

		$actual = WP_Theme_JSON::get_from_editor_settings( $input );

		$this->assertEqualSetsWithIndex( $expected, $actual );
	}

	/**
	 * @ticket 52991
	 */
	public function test_get_editor_settings_no_theme_support() {
		$input = array(
			'__unstableEnableFullSiteEditingBlocks' => false,
			'disableCustomColors'                   => false,
			'disableCustomFontSizes'                => false,
			'disableCustomGradients'                => false,
			'enableCustomLineHeight'                => false,
			'enableCustomUnits'                     => false,
			'imageSizes'                            => array(
				array(
					'slug' => 'thumbnail',
					'name' => 'Thumbnail',
				),
				array(
					'slug' => 'medium',
					'name' => 'Medium',
				),
				array(
					'slug' => 'large',
					'name' => 'Large',
				),
				array(
					'slug' => 'full',
					'name' => 'Full Size',
				),
			),
			'isRTL'                                 => false,
			'maxUploadFileSize'                     => 123,
		);

		$expected = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
			'settings' => array(
				'color'      => array(
					'custom'         => true,
					'customGradient' => true,
				),
				'spacing'    => array(
					'units' => false,
				),
				'typography' => array(
					'customFontSize'   => true,
					'customLineHeight' => false,
				),
			),
		);

		$actual = WP_Theme_JSON::get_from_editor_settings( $input );

		$this->assertEqualSetsWithIndex( $expected, $actual );
	}

	/**
	 * @ticket 52991
	 */
	public function test_get_editor_settings_blank() {
		$expected = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
			'settings' => array(),
		);
		$actual   = WP_Theme_JSON::get_from_editor_settings( array() );

		$this->assertSameSetsWithIndex( $expected, $actual );
	}
}
