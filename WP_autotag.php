<?php

ini_set('display_errors', 1);

/*
*
*
* Plugin Name: wp_autoTAG
* Plugin URI: http://pruebawpplugins.esy.es/
* Description: A plugin to detect an add new TAGS depending on the post content
* Version: 1.0
* Author: Miquel Correa
* Author URI: http://barranquismo.com.es
* License: MIT
**/
/*  Copyright (c) 2015 8mikelx8

  Permission is hereby granted, free of charge, to any person obtaining a copy
  of this software and associated documentation files (the "Software"), to deal
  in the Software without restriction, including without limitation the rights
  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
  copies of the Software, and to permit persons to whom the Software is
  furnished to do so, subject to the following conditions:

  The above copyright notice and this permission notice shall be included in all
  copies or substantial portions of the Software.

  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
  SOFTWARE.
*/

add_action( 'admin_menu', 'wp_autoTAG' );

function wp_autoTAG() {
	add_management_page( 'Opciones WP autoTAG', 'Wp autoTAG', 'manage_options', 'wp_autoTAG', 'wp_autoTAG_options' );
}

function wp_autoTAG_remoteApiCall($content, $url, $valueToken) {
	$data = array('text_list' => array($content));

	$options = array(
		'http' => array(
			'header'  => "Content-type: application/json\r\nAuthorization:token $valueToken\r\n",
			'method'  => 'POST',
			'content' => json_encode($data),
		)
	);

	$context  = stream_context_create($options);

	return file_get_contents($url, false, $context);
}

function wp_autoTAG_options() {
	if (!current_user_can('manage_options')) {
		wp_die( __('PequeÃ±o padawan... debes utilizar la fuerza para entrar aquí­.') );
	}

	function saveoptions(){
		//language
		if (isset($_POST['language'])) {
			$idioma = $_POST['language'];
			add_option('language1', $idioma);
			update_option('language1', $idioma);

			if ($idioma === 'Español') {
				$idiomax = 'English';
			} else {
				$idiomax = 'Español';
			}

			add_option('language2', $idiomax);
			update_option('language2', $idiomax);
		}

		//API token
		$optionapi = get_option('apitoken','0');
		$valuetoken = $_POST['apitoken'];

		if (isset($_POST['apitoken'])) {
			if (!($optionapi === $valuetoken)) {
				add_option('apitoken', $valuetoken);
				update_option('apitoken', $valuetoken);
			}
		}

		//Keywords
		if (isset($_POST['keywords'])) {
			add_option('keywords', true);
			update_option('keywords', true);
		} else {
			add_option('keywords', false);
			update_option('keywords', false);
		}

		if (isset($_POST['relevancia'])) {
			$limit = $_POST['relevancia'];
			add_option('relevance', $limit);
			update_option('relevance', $limit);
		}

		//Entities
		if (isset($_POST['personas'])) {
			add_option('people', true);
			update_option('people', true);
		} else {
			add_option('people', false);
			update_option('people', false);
		}

		if (isset($_POST['lugares'])) {
			add_option('places', true);
			update_option('places', true);
		} else {
			add_option('places', false);
			update_option('places', false);
		}

		if (isset($_POST['organizaciones'])) {
			add_option('organization', true);
			update_option('organization', true);
		} else {
			add_option('organization', false);
			update_option('organization', false);
		}

		if (isset($_POST['otros'])) {
			add_option('other', true);
			update_option('other', true);
		} else {
			add_option('other', false);
			update_option('other', false);
		}
	}

	if (isset($_POST['savechanges'])) {
		saveoptions();
	} // end savechanges

	if (isset($_POST['gentags'])) {
		saveoptions();

		$limite = $_POST['relevancia'];
		$valuetoken = $_POST['apitoken'];
		$optionapi = get_option('apitoken','0');

		$idioma1 = get_option('language1', 'Español');

		if ($idioma1=='Español') {
			$analizando = "Analizando...";
			$postags = "<b>Todos los POST ya tienen Tags.</b>";
			$creados = "TAGS generados";
			$engine = "https://api.monkeylearn.com/v2/extractors/ex_Kc8uzhSi/extract/";
			$keywords = "https://api.monkeylearn.com/v2/extractors/ex_eV2dppYE/extract/";
			$location = "LUG";
			$organization = "ORG";
			$person = "PERS";
			$other = "OTROS";
		} else {
			$analizando = "Analyzing...";
			$postags = "<b>All POST have already Tags.</b>";
			$creados = "TAGS have been generated";
			$engine = "https://api.monkeylearn.com/v2/extractors/ex_isnnZRbS/extract/";
			$keywords = "https://api.monkeylearn.com/v2/extractors/ex_y7BPYzNG/extract/";
			$location = "LOCATION";
			$organization = "ORGANIZATION";
			$person = "PERSON";
			$other = "OTRO";
		}

		echo $analizando;

		if (isset($_POST['apitoken'])) {
			if (!($optionapi === $valuetoken)) {
				add_option('apitoken', $valuetoken);
				update_option('apitoken', $valuetoken);
			}
		}

		$all_tags = get_tags();
		$listatags = array();

		foreach( $all_tags as $tag ) {
			array_push($listatags, $tag->term_id);
		}

		//THE LOOP
		$posts = get_posts(
			array('numberposts'=>-1, 'posts_per_page'=>-1,'tag__not_in'=>$listatags)
		);

		if ($posts) {
			$contador = 0;

			foreach ($posts as $post) {
				$arraytags = array();
				$post_id = $post->ID;
				$content = strip_tags($post->post_content);

				//FUNCIÓN DE ANALISIS DE TEXTO

				//ANALISIS DE ENTIDADES
				if(
					isset($_POST['personas']) || isset($_POST['lugares']) ||
					isset($_POST['organizaciones']) || isset($_POST['otros'])
				) {
					$url = $engine;

					$result = wp_autoTAG_remoteApiCall($content, $url, $valuetoken);

					$jsonIterator = new RecursiveIteratorIterator(
						new RecursiveArrayIterator(json_decode($result, TRUE)),
						RecursiveIteratorIterator::SELF_FIRST
					);

					$push = false;

					foreach ($jsonIterator as $key => $val) {
						if (is_array($val)) {
							// Nothing to do here?
						} else {
							if (isset($_POST['personas'])) {
								if ($key === 'tag' && $val === $person) {
									$push = true;
								}
							}

							if (isset($_POST['lugares'])) {
								if ($key === 'tag' && $val === $location) {
									$push = true;
								}
							}

							if (isset($_POST['organizaciones'])) {
								if ($key === 'tag' && $val === $organization) {
									$push = true;
								}
							}

							if (isset($_POST['otros'])) {
								if ($key === 'tag' && $val === $other) {
									$push = true;
								}
							}

							if ($key === 'entity' && $push == true) {
								$entity = $val;
								array_push($arraytags, $entity);
								$push = false;
							}
						}
					}

					echo ".";
				}

				if (isset($_POST['keywords'])) {
					// ANALISIS DE KEYWORDS CON RELEVANCIA
					$limite = $_POST['relevancia'];
					$url = $keywords;
					$result2 = wp_autoTAG_remoteApiCall($content, $url, $valuetoken);

					$jsonIterator = new RecursiveIteratorIterator(
						new RecursiveArrayIterator(json_decode($result2, TRUE)),
						RecursiveIteratorIterator::SELF_FIRST
					);

					foreach ($jsonIterator as $key => $val) {
						if(is_array($val)) {
							// Nothing to do here?
						} else {
							//COMPROBAR RELEVANCIA POR ENCIMA DEL LIMITE ESTABLECIDO
							if ($key ==='relevance') {
								if (floatval($val) > floatval($limite)) {
									$push = true;
								} else {
									$push = false;
								}
							}

							//AÑADIR KEYWORDS RELEVANTES AL ARRAY
							if ($key ==='keyword') {
								if ($push == true) {
									array_push($arraytags, $val);
								}
							}
						}
					}

					echo ".";
				} // final de if(isset(post[keywords]))

				wp_set_post_tags( $post_id, $arraytags);
				echo "!";
			} // final while(have posts())
		} else {
			echo $postags;
		}
?>
		<div class="updated">
			<p>
				<strong>
					<?php _e($creados); ?>
				</strong>
			</p>
		</div>
<?php
	}
?>
		<form name="form1" method="post" action="">
<?php
	echo '<div class="wrap">';
	echo "<h2>" . __( 'auto TAG');

	$idioma1 = get_option('language1','English');
	$idioma2 = get_option('language2','Español');

	if ($idioma1 == 'Español') {
		$claveapi = 'Clave de API';
		$tagskeyword = 'TAGS por palabra clave';
		$tagsentity = 'TAGS por entidades';
		$nokey = "Si no dispone de Clave puede conseguir una <a href='https://app.monkeylearn.com/accounts/register'/>aquí</a>";
		$minimrel = "Relevancia mínima: ";
		$pers = "PERSONAS";
		$org = "ORGANIZACIONES";
		$lug = "LUGARES";
		$otr = "OTROS";
		$resumen = "<p style='text-align:justify'>WP_autotag analiza el contenido de tus Post y genera TAGS acordes a éste. En las opciones puede decidir si quiere incorporar las palabras clave del texto como tags, así como la relevancia mínima a partir de la cual se incorporan. También puede decidir qué clase de entidades (personas, lugares, etc.) quiere incorporar a los tags. El plugin depende de un servicio externo por lo que es necesario adquirir una clave (gratuita) para usarlo. Esto puede limitar también el número de post que el plugin puede actualizar cada vez. El plugin solo creará tags para los post que no tengan ninguno todavía.</p>";
		$generate = "Generar TAGS";
		$savechang = "Guardar preferencias";
    } else {
		$claveapi = 'API Key';
		$tagskeyword = 'TAGS for Keywords';
		$tagsentity = 'TAGS for entities';
		$nokey = "If you don't have an API Key you can get one <a href='https://app.monkeylearn.com/accounts/register'/>here</a>";
		$minimrel = "Minimal relevance: ";
		$pers = "PERSON";
		$org = "ORGANIZATION";
		$lug = "LOCATION";
		$otr = "OTHER";
		$resumen = "<p style='text-align:justify'>WP_autotag analyze the content of your Posts and generate TAGS in base to this analysis. In the options you can choose if you want keywords as tags, as well as the minimal relevance of the keywords to be chosen as a tag. You can also choose which kind of entities (person, location, etc.) do you want as tags. this plugin depends on an external service and you have to aquire a key (free) to use it. It also affect the number of posts that can be analyzed each time. From here you can only analyze posts that doesn't have any tag already.</p>";
		$generate = "Generate TAGS";
		$savechang = "Save settings";
	}

	$keywordsopt = get_option('keywords', 'true');
	$relevanceopt = get_option('relevance', '0.5');
	$peopleopt = get_option('people', 'true');
	$placesopt = get_option('places', 'true');
	$otheropt = get_option('other', 'true');
	$organizationsopt = get_option('organization', 'true');
?>
	<select name="language">
		<option><?php echo $idioma1;?></option>
		<option><?php echo $idioma2;?></option>
	</select></h2>
<?php
	$optionapi = get_option('apitoken','0');
	echo $resumen;
?>
	<fieldset style="border:solid black 1px; padding: 15px;">
		<p style="margin:0 1em 0 2em;">
			<h3><span style="margin-right:1em;"><?php echo $claveapi;?></span></h3>
			<input type="text" name="apitoken" size="42" value="<?php echo $optionapi ?>"/></br>
			<?php echo $nokey;?>
		</p>
	</fieldset>
	<fieldset style="border:solid black 1px; padding: 15px;">
		<p style="margin:0 1em 0 2em;">
			<h3><span style="margin-right:1em;"><?php echo $tagskeyword;?></span> <input type="checkbox" name="keywords" <?php if($keywordsopt){echo "checked=true"; } ?> /></h3>
			<?php _e($minimrel); ?>
			<input type="number" name="relevancia" min="0.1" max="1" step="0.1" value="<?php echo $relevanceopt ?>"/>
		</p>
	</fieldset>
	<fieldset style="border:solid black 1px; padding: 15px;">
		<p style="margin:0 1em 0 2em;">
			<h3> <?php echo $tagsentity;?></h3>
			<ul style="width:100%; list-style:none; margin:0; padding:0; float:left; text-align: left;">
				<li style="float:left; width:25%; margin:0; paddind:0; list-style: none;">
					<input type="checkbox" name="personas" <?php if($peopleopt){echo "checked=true"; } ?>/>
					<?php _e($pers); ?>
				</li>
				<li style="float:left; width:25%; margin:0; paddind:0; list-style: none;">
					<input type="checkbox" name="lugares" <?php if($placesopt){echo "checked=true"; } ?>/>
					<?php _e($lug); ?>
				</li>
				<li style="float:left; width:25%; margin:0; paddind:0; list-style: none;">
					<input type="checkbox" name="organizaciones" <?php if($organizationsopt){echo "checked=true"; } ?>/>
					<?php _e($org); ?>
				</li>
				<li style="float:left; width:25%; margin:0; paddind:0; list-style: none;<?php if($otr == "OTHER"){
					echo "display:none";
				} ?>">
					<input type="checkbox" name="otros" <?php if($otheropt){echo "checked=true"; } ?>/>
					<?php _e($otr); ?>
				</li>
			</ul>
		</p>
	</fieldset>
	<p class="submit">
		<input type="submit" name="savechanges" class="button-primary" style="margin: 0 2em 0 0;" value="<?php echo $savechang; ?>" />
		<input type="submit" name="gentags" class="button-primary" value="<?php echo $generate; ?>" />
	</p>
</form>
</div>

<?php
}
//STARTS CODE FOR BULK ACTIONS**************************************************************************
//******************************************************************************************************
//******************************************************************************************************
//******************************************************************************************************

if (!class_exists('Add_Tags_Bulk_Action')) {

	class Add_Tags_Bulk_Action {

		public function __construct() {
			if(is_admin()) {
				// admin actions/filters
				add_action('admin_footer-edit.php', array(&$this, 'custom_bulk_admin_footer'));
				add_action('load-edit.php',         array(&$this, 'custom_bulk_action'));
				add_action('admin_notices',         array(&$this, 'custom_bulk_admin_notices'));
			}
		}

		/**
		 * Step 1: add the custom Bulk Action to the select menus
		 */
		function custom_bulk_admin_footer() {
			global $post_type;

			if($post_type == 'post') {
				$idioma1 = get_option('language1','English');
				if($idioma1 == 'English') {
					$addtags = "Add tags";
				} else {
					$addtags = "Añadir tags";
				}
				?>
					<script type="text/javascript">
						jQuery(document).ready(function() {
							jQuery('<option>').val('add_tag').text('<?php _e( $addtags )?>').appendTo("select[name='action']");
							jQuery('<option>').val('add_tag').text('<?php _e( $addtags )?>').appendTo("select[name='action2']");
						});
					</script>
				<?php
			}
		}

		function custom_bulk_action() {
			global $typenow;
			$post_type = $typenow;

			if($post_type == 'post') {
				// get the action
				$wp_list_table = _get_list_table('WP_Posts_List_Table');
				$action = $wp_list_table->current_action();

				$allowed_actions = array("add_tag");
				if(!in_array($action, $allowed_actions)) return;

				// security check
				check_admin_referer('bulk-posts');

				// make sure ids are submitted.
				if(isset($_REQUEST['post'])) {
					$post_ids = array_map('intval', $_REQUEST['post']);
				}

				if(empty($post_ids)) return;

				$sendback = remove_query_arg( array('exported', 'untrashed', 'deleted', 'ids'), wp_get_referer() );
				if ( ! $sendback )
					$sendback = admin_url( "edit.php?post_type=$post_type" );

				$pagenum = $wp_list_table->get_pagenum();
				$sendback = add_query_arg( 'paged', $pagenum, $sendback );

				switch($action) {
					case 'add_tag':
						$tags_added = 0;

						foreach( $post_ids as $post_id ) {
							// ANALYSIS CODE
							if($valuetoken != 'notoken') {
								$valuetoken = get_option('apitoken', 'notoken');
								$keywordsopt = get_option('keywords', 'true');
								$relevanceopt = get_option('relevance', '0.5');
								$peopleopt = get_option('people', 'true');
								$placesopt = get_option('places', 'true');
								$otheropt = get_option('other', 'true');
								$organizationsopt = get_option('organization', 'true');

								$idioma1 = get_option('language1','English');
								$content_post = get_post($post_id);
								$content = strip_tags($content_post->post_content);
								$arraytags = array();

								if($idioma1 === 'English') {
									$engine = "https://api.monkeylearn.com/v2/extractors/ex_isnnZRbS/extract/";
									$keywords = "https://api.monkeylearn.com/v2/extractors/ex_y7BPYzNG/extract/";
									$location = "LOCATION";
									$organization = "ORGANIZATION";
									$person = "PERSON";
									$other = "OTRO";
								} elseif($idioma1 === 'Español') {
									$engine = "https://api.monkeylearn.com/v2/extractors/ex_Kc8uzhSi/extract/";
									$keywords = "https://api.monkeylearn.com/v2/extractors/ex_eV2dppYE/extract/";
									$location = "LUG";
									$organization = "ORG";
									$person = "PERS";
									$other = "OTROS";
								}

								//ENTITIES ANALYSIS
								if($peopleopt || $organizationsopt || $otheropt || $placesopt) {
									$url = $engine;
									$result = wp_autoTAG_remoteApiCall($content, $url, $valuetoken);

									$jsonIterator = new RecursiveIteratorIterator(
										new RecursiveArrayIterator(json_decode($result, true)),
										RecursiveIteratorIterator::SELF_FIRST
									);

									$push = false;

									foreach ($jsonIterator as $key => $val) {
										if (is_array($val)) {
											// Nothing to do here?
										} else {
											if ($peopleopt) {
												if ($key === 'tag' && $val === $person) {
													$push = true;
												}
											}

											if ($placesopt) {
												if ($key === 'tag' && $val === $location) {
													$push = true;
												}
											}

											if ($organizationsopt) {
												if ($key === 'tag' && $val === $organization) {
													$push = true;
												}
											}

											if ($otheropt) {
												if ($key === 'tag' && $val === $other) {
													$push = true;
												}
											}

											if ($key === 'entity' && $push == true) {
												$entity = $val;
												array_push($arraytags, $entity);
												$push = false;
											}
										}
									}
								}

								// RELEVANT KEYWORDS ANALYSIS
								if ($keywordsopt) {
									$limite = $relevanceopt;
									$url = $keywords;

									$result2 = wp_autoTAG_remoteApiCall($content, $url, $valuetoken);

									$jsonIterator = new RecursiveIteratorIterator(
										new RecursiveArrayIterator(json_decode($result2, TRUE)),
										RecursiveIteratorIterator::SELF_FIRST
									);

									foreach ($jsonIterator as $key => $val) {
										if (is_array($val)) {
											// Nothing to do here?
										} else {
											// CHECK RELEVANCE
											if ($key === 'relevance') {
												if (floatval($val) > floatval($limite)) {
													$push = true;
												} else {
													$push = false;
												}
											}

											// ADD KEYWORDS TO ARRAY
											if ($key === 'keyword') {
												if($push == true) {
													array_push($arraytags, $val);
												}
											}
										}
									}
								}

								wp_set_post_tags( $post_id, $arraytags);
							}

							if ( !$this->perform_export($post_id) ) {
								wp_die( __('Error adding tags.') );
							}

							$tags_added++;
						}

						$sendback = add_query_arg(
							array('exported' => $tags_added, 'ids' => join(',', $post_ids) ), $sendback
						);
						break;

					default:
						return;
				}

				$sendback = remove_query_arg(
					array('action', 'action2', 'tags_input', 'post_author', 'comment_status', 'ping_status', '_status',  'post', 'bulk_edit', 'post_view'),
					$sendback
				);

				wp_redirect($sendback);
				exit();
			}
		}


		/**
		 * Display an admin notice on the Posts page after exporting
		 */
		function custom_bulk_admin_notices() {
			global $post_type, $pagenow;

			if($pagenow == 'edit.php' && $post_type == 'post' && isset($_REQUEST['exported']) && (int) $_REQUEST['exported']) {
				$message = sprintf( _n( 'Tags added.', 'Tags added to %s posts.', $_REQUEST['exported'] ), number_format_i18n( $_REQUEST['exported'] ) );
				echo "<div class=\"updated\"><p>{$message}</p></div>";
			}
		}

		function perform_export($post_id) {
			return true;
		}
	}
}

new Add_Tags_Bulk_Action();
