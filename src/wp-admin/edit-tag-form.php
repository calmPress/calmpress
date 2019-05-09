<?php
/**
 * Edit tag form for inclusion in administration panels.
 *
 * @package WordPress
 * @subpackage Administration
 */

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

// Back compat hooks
if ( 'category' == $taxonomy ) {
	/**
	 * Fires before the Edit Category form.
	 *
	 * @since 2.1.0
	 * @deprecated 3.0.0 Use {$taxonomy}_pre_edit_form instead.
	 *
	 * @param WP_Term $tag Current category term object.
	 */
	do_action( 'edit_category_form_pre', $tag );
} else {
	/**
	 * Fires before the Edit Tag form.
	 *
	 * @since 2.5.0
	 * @deprecated 3.0.0 Use {$taxonomy}_pre_edit_form instead.
	 *
	 * @param WP_Term $tag Current tag term object.
	 */
	do_action( 'edit_tag_form_pre', $tag );
}

/**
 * Use with caution, see https://codex.wordpress.org/Function_Reference/wp_reset_vars
 */
wp_reset_vars( array( 'wp_http_referer' ) );

$wp_http_referer = remove_query_arg( array( 'action', 'message', 'tag_ID' ), $wp_http_referer );

/** Also used by Edit Tags */
require_once( ABSPATH . 'wp-admin/includes/edit-tag-messages.php' );

/**
 * Fires before the Edit Term form for all taxonomies.
 *
 * The dynamic portion of the hook name, `$taxonomy`, refers to
 * the taxonomy slug.
 *
 * @since 3.0.0
 *
 * @param WP_Term $tag      Current taxonomy term object.
 * @param string  $taxonomy Current $taxonomy slug.
 */
do_action( "{$taxonomy}_pre_edit_form", $tag, $taxonomy ); ?>

<div class="wrap">
<h1><?php echo $tax->labels->edit_item; ?></h1>

<?php
$class = ( isset( $msg ) && 5 === $msg ) ? 'error' : 'success';

if ( $message ) {
	?>
<div id="message" class="notice notice-<?php echo $class; ?>">
	<p><strong><?php echo $message; ?></strong></p>
	<?php if ( $wp_http_referer ) { ?>
	<p><a href="<?php echo esc_url( wp_validate_redirect( esc_url_raw( $wp_http_referer ), admin_url( 'term.php?taxonomy=' . $taxonomy ) ) ); ?>">
		<?php echo esc_html( $tax->labels->back_to_items ); ?>
	</a></p>
	<?php } ?>
</div>
	<?php
}
?>

<div id="ajax-response"></div>

<form name="edittag" id="edittag" method="post" action="edit-tags.php" class="validate"
<?php
/**
 * Fires inside the Edit Term form tag.
 *
 * The dynamic portion of the hook name, `$taxonomy`, refers to the taxonomy slug.
 *
 * @since 3.7.0
 */
do_action( "{$taxonomy}_term_edit_form_tag" );
?>
>
<input type="hidden" name="action" value="editedtag"/>
<input type="hidden" name="tag_ID" value="<?php echo esc_attr( $tag_ID ); ?>"/>
<input type="hidden" name="taxonomy" value="<?php echo esc_attr( $taxonomy ); ?>"/>
<?php
wp_original_referer_field( true, 'previous' );
wp_nonce_field( 'update-tag_' . $tag_ID );

/**
 * Fires at the beginning of the Edit Term form.
 *
 * At this point, the required hidden fields and nonces have already been output.
 *
 * The dynamic portion of the hook name, `$taxonomy`, refers to the taxonomy slug.
 *
 * @since 4.5.0
 *
 * @param WP_Term $tag      Current taxonomy term object.
 * @param string  $taxonomy Current $taxonomy slug.
 */
do_action( "{$taxonomy}_term_edit_form_top", $tag, $taxonomy );

$tag_name_value = '';
if ( isset( $tag->name ) ) {
	$tag_name_value = esc_attr( $tag->name );
}
?>
	<table class="form-table">
		<tr class="form-field form-required term-name-wrap">
			<th scope="row"><label for="name"><?php _ex( 'Name', 'term name' ); ?></label></th>
			<td><input name="name" id="name" type="text" value="<?php echo $tag_name_value; ?>" size="40" aria-required="true" />
			<p class="description"><?php _e( 'The name is how it appears on your site.' ); ?></p></td>
		</tr>
<?php if ( ! global_terms_enabled() ) { ?>
		<tr class="form-field term-slug-wrap">
			<th scope="row"><label for="slug"><?php _e( 'Slug' ); ?></label></th>
			<?php
			/**
			 * Filters the editable slug.
			 *
			 * Note: This is a multi-use hook in that it is leveraged both for editable
			 * post URIs and term slugs.
			 *
			 * @since 2.6.0
			 * @since 4.4.0 The `$tag` parameter was added.
			 *
			 * @param string          $slug The editable slug. Will be either a term slug or post URI depending
			 *                              upon the context in which it is evaluated.
			 * @param WP_Term|WP_Post $tag  Term or WP_Post object.
			 */
			$slug = isset( $tag->slug ) ? apply_filters( 'editable_slug', $tag->slug, $tag ) : '';
			?>
			<td><input name="slug" id="slug" type="text" value="<?php echo esc_attr( $slug ); ?>" size="40" />
			<p class="description"><?php _e( 'The &#8220;slug&#8221; is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.' ); ?></p></td>
		</tr>
<?php } ?>
<?php if ( is_taxonomy_hierarchical( $taxonomy ) ) : ?>
		<tr class="form-field term-parent-wrap">
			<th scope="row"><label for="parent"><?php echo esc_html( $tax->labels->parent_item ); ?></label></th>
			<td>
				<?php
				$dropdown_args = array(
					'hide_empty'       => 0,
					'hide_if_empty'    => false,
					'taxonomy'         => $taxonomy,
					'name'             => 'parent',
					'orderby'          => 'name',
					'selected'         => $tag->parent,
					'exclude_tree'     => $tag->term_id,
					'hierarchical'     => true,
					'show_option_none' => __( 'None' ),
				);

				/** This filter is documented in wp-admin/edit-tags.php */
				$dropdown_args = apply_filters( 'taxonomy_parent_dropdown_args', $dropdown_args, $taxonomy, 'edit' );
				wp_dropdown_categories( $dropdown_args );
				?>
				<?php if ( 'category' == $taxonomy ) : ?>
					<p class="description"><?php _e( 'Categories, unlike tags, can have a hierarchy. You might have a Jazz category, and under that have children categories for Bebop and Big Band. Totally optional.' ); ?></p>
				<?php else : ?>
					<p class="description"><?php _e( 'Assign a parent term to create a hierarchy. The term Jazz, for example, would be the parent of Bebop and Big Band.' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
<?php endif; // is_taxonomy_hierarchical() ?>
		<tr class="form-field term-description-wrap">
			<th scope="row"><label for="description"><?php _e( 'Description' ); ?></label></th>
			<td><textarea name="description" id="description" rows="5" cols="50" class="large-text"><?php echo $tag->description; // textarea_escaped ?></textarea>
			<p class="description"><?php _e( 'The description is not prominent by default; however, some themes may show it.' ); ?></p></td>
		</tr>
		<?php
		if ( \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME === $taxonomy ) {
			$author = new \calmpress\post_authors\Taxonomy_Based_Post_Author( $tag );
			$image  = $author->image();

			$img_url       = '';
			if ( $image ) {
				$img_url = wp_get_attachment_image_url( $image->ID, array( 150, 150 ) );
				// In case the attachment was deleted for whatever reason pretend
				// was never set.
				if ( ! $img_url ) {
					$img_url = '';
				}
			}

			$deselect_style = '';
			if ( ! $img_url ) {
				$deselect_style = ' style="display:none"';
			}
			?>
			<style>
			#featured-image {
				max-height: 150px;
				max-width: 150px;
				height: auto;
				width: auto;
				border: 2px solid #ccc;
			}
			</style>
			<tr class="form-field featured-image-wrap">
				<th scope="row"><label for="featured-image"><?php esc_html_e( 'Image' ); ?></label></th>
				<td>
					<div>
						<img id="featured-image" <?php echo $deselect_style; ?> src="<?php echo esc_url( $img_url ); ?>" />
						<input type="hidden" name="featured-image-id" id="featured-image-id" value="<?php echo esc_attr( $image->ID ); ?>" />
					</div>
					<button type="button" class="button featured-image-choose">
						<?php esc_html_e( 'Select Image' ); ?>
					</button>
					<button type="button" class="button featured-image-remove"<?php echo $deselect_style; ?>>
						<?php esc_html_e( 'Deselect The Image' ); ?>
					</button>
				</td>
				<p class="description"><?php esc_html_e( 'An image that can be used to identify the author where appropriate.' ); ?></p></td>
			</tr>
			<?php
		}
		// Back compat hooks
		if ( 'category' == $taxonomy ) {
			/**
			 * Fires after the Edit Category form fields are displayed.
			 *
			 * @since 2.9.0
			 * @deprecated 3.0.0 Use {$taxonomy}_edit_form_fields instead.
			 *
			 * @param WP_Term $tag Current category term object.
			 */
			do_action( 'edit_category_form_fields', $tag );
		} else {
			/**
			 * Fires after the Edit Tag form fields are displayed.
			 *
			 * @since 2.9.0
			 * @deprecated 3.0.0 Use {$taxonomy}_edit_form_fields instead.
			 *
			 * @param WP_Term $tag Current tag term object.
			 */
			do_action( 'edit_tag_form_fields', $tag );
		}
		/**
		 * Fires after the Edit Term form fields are displayed.
		 *
		 * The dynamic portion of the hook name, `$taxonomy`, refers to
		 * the taxonomy slug.
		 *
		 * @since 3.0.0
		 *
		 * @param WP_Term $tag      Current taxonomy term object.
		 * @param string  $taxonomy Current taxonomy slug.
		 */
		do_action( "{$taxonomy}_edit_form_fields", $tag, $taxonomy );
		?>
	</table>
<?php
// Back compat hooks
if ( 'category' == $taxonomy ) {
	/** This action is documented in wp-admin/edit-tags.php */
	do_action( 'edit_category_form', $tag );
} else {
	/**
	 * Fires at the end of the Edit Term form.
	 *
	 * @since 2.5.0
	 * @deprecated 3.0.0 Use {$taxonomy}_edit_form instead.
	 *
	 * @param WP_Term $tag Current taxonomy term object.
	 */
	do_action( 'edit_tag_form', $tag );
}
/**
 * Fires at the end of the Edit Term form for all taxonomies.
 *
 * The dynamic portion of the hook name, `$taxonomy`, refers to the taxonomy slug.
 *
 * @since 3.0.0
 *
 * @param WP_Term $tag      Current taxonomy term object.
 * @param string  $taxonomy Current taxonomy slug.
 */
do_action( "{$taxonomy}_edit_form", $tag, $taxonomy );
?>

<div class="edit-tag-actions">

	<?php submit_button( __( 'Update' ), 'primary', null, false ); ?>

	<?php if ( current_user_can( 'delete_term', $tag->term_id ) ) : ?>
		<span id="delete-link">
			<a class="delete" href="<?php echo admin_url( wp_nonce_url( "edit-tags.php?action=delete&taxonomy=$taxonomy&tag_ID=$tag->term_id", 'delete-tag_' . $tag->term_id ) ); ?>"><?php _e( 'Delete' ); ?></a>
		</span>
	<?php endif; ?>

</div>

</form>
</div>

<?php if ( ! wp_is_mobile() ) : ?>
<script type="text/javascript">
try{document.forms.edittag.name.focus();}catch(e){}
</script>
	<?php
endif;
