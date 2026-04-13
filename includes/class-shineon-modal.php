<?php
/**
 * ShineOn Modal Handler
 *
 * Handles rendering and functionality of the modal for viewing product renders.
 *
 * @package ShineOn_For_WooCommerce
 */

class ShineOn_Modal {

	public function __construct() {
		$this->init();
	}

	/**
	 * Initialize AJAX handlers.
	 */
	public function init() {
		add_action( 'wp_ajax_shineon_generate_renders', array( $this, 'ajax_generate_renders' ) );
		add_action( 'wp_ajax_shineon_make_render', array( $this, 'ajax_make_render' ) );
	}

	/**
	 * AJAX handler to fetch render IDs only.
	 */
	public function ajax_generate_renders() {
		@ob_end_clean();

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'shineon_nonce' ) ) {
			wp_send_json_error( 'Security check failed' );
		}

		if ( ! isset( $_POST['template_id'] ) || ! isset( $_POST['image_url'] ) ) {
			wp_send_json_error( 'Missing required parameters' );
		}

		$template_id = intval( $_POST['template_id'] );
		$image_url = sanitize_url( $_POST['image_url'] );

		$render_ids = $this->fetch_renders( $template_id );

		if ( false === $render_ids || empty( $render_ids ) ) {
			wp_send_json_error( 'Failed to fetch render IDs' );
		}

		wp_send_json_success( array( 'render_ids' => $render_ids ) );
	}

	/**
	 * AJAX handler to make a single render call.
	 */
	public function ajax_make_render() {
		@ob_end_clean();

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'shineon_nonce' ) ) {
			wp_send_json_error( 'Security check failed' );
		}

		if ( ! isset( $_POST['render_id'] ) || ! isset( $_POST['image_url'] ) ) {
			wp_send_json_error( 'Missing parameters' );
		}

		$render_id = intval( $_POST['render_id'] );
		$image_url = sanitize_url( $_POST['image_url'] );

		$result = $this->make_render( $render_id, $image_url );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		// Extract image URL from response
		$data_res = isset( $result['data'] ) ? $result['data'] : $result;
		$image_src = null;
		
		if ( isset( $data_res['render']['make']['src'] ) ) {
			$image_src = $data_res['render']['make']['src'];
		} elseif ( isset( $data_res['make']['src'] ) ) {
			$image_src = $data_res['make']['src'];
		} elseif ( isset( $data_res['render']['layers']['main'] ) ) {
			$image_src = $data_res['render']['layers']['main'];
		}

		wp_send_json_success( array(
			'render_id' => $render_id,
			'url'       => $image_src,
		) );
	}

	private function make_render( $render_id, $image_url ) {
		return ShineOn_Settings::request( '/renders/' . sanitize_text_field( $render_id ) . '/make', 'POST', array( 'src' => $image_url ) );
	}

	public function fetch_renders( $product_template_id ) {
		$data = ShineOn_Settings::request( '/product_templates/' . intval( $product_template_id ) );

		if ( is_wp_error( $data ) || ! isset( $data['render_ids'] ) ) {
			return false;
		}

		return $data['render_ids'];
	}

	/**
	 * Render the renders modal HTML, styles, and scripts.
	 */
	public function render() {
		?>
		<!-- Renders Modal -->
		<div id="renders-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
			<div style="background: white; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); max-width: 700px; width: 90%; max-height: 80vh; overflow-y: auto; position: relative;">
				<div style="padding: 20px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">
					<h2 style="margin: 0;"><span id="modal-title-prefix">Renders</span> for <span id="modal-sku">SKU</span></h2>
					<button onclick="closeRendersModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
				</div>
				<div style="padding: 20px;">
					<!-- Image URL Input -->
					<div style="margin-bottom: 15px;">
						<div style="display: flex; gap: 10px; margin-bottom: 10px;">
							<input 
								type="text" 
								id="image-url-input" 
								placeholder="https://s3.amazonaws.../image.png" 
								style="flex: 1; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;"
							>
							<button 
								id="modal-action-btn"
								onclick="handleModalAction()" 
								style="padding: 8px 16px; background: #0073aa; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;"
							>
								Generate Renders
							</button>
						</div>
						<div id="no-artwork-wrapper" style="margin-bottom: 10px;">
							<label style="font-size:12px; display:flex; align-items:center; gap:8px; cursor:pointer; color: #444;">
								<input type="checkbox" id="no-artwork-checkbox" onchange="document.getElementById('image-url-input').disabled = this.checked;" />
								This product does not need a rendered image
							</label>
						</div>
						<p id="modal-instruction-text" style="font-size:12px; color:#666; font-style:italic; margin: 0;">Enter an image URL and click "Import + Images" to start the process, if applicable.</p>
						<p id="shineon-progress-text" style="font-size:12px; color:#0073aa; font-weight:bold; margin-top: 5px;"></p>
					</div>

					<!-- Loading State -->
					<div id="modal-loading" style="display: none; text-align: center; padding: 20px; color: #666;">
						<p class="shineon-loading-text">Loading renders...</p>
					</div>

					<!-- Renders Gallery -->
					<div id="renders-gallery" style="display: flex; flex-wrap: wrap; gap: 15px; justify-content: center;">
						<!-- Renders will be inserted here -->
					</div>
				</div>
			</div>
		</div>

		<style>
			#renders-modal {
				display: none !important;
			}
			#renders-modal.show {
				display: flex !important;
			}
			#modal-action-btn:disabled {
				opacity: 0.6;
				cursor: not-allowed;
				background: #ccc !important;
			}
			.render-item {
				border: 1px solid #ddd;
				border-radius: 4px;
				overflow: hidden;
				background: #f5f5f5;
				width: 200px;
				flex-shrink: 0;
			}
			.render-item img {
				width: 200px;
				height: 200px;
				object-fit: cover;
				display: block;
			}
			.render-item-id {
				padding: 8px 12px;
				background: #f9f9f9;
				border-top: 1px solid #ddd;
				font-size: 12px;
				color: #666;
				text-align: center;
			}
			@keyframes shineon-pulse {
				0%, 100% { opacity: 1; }
				50% { opacity: 0.3; }
			}
			.shineon-loading-text {
				animation: shineon-pulse 1.4s ease-in-out infinite;
				margin: 0;
			}
		</style>

		<script>
			function openRendersModal(element, mode = 'renders') {
				const templateId = element.getAttribute('data-template-id');
				const sku = element.getAttribute('data-sku');
				const modal = document.getElementById('renders-modal');
				const actionBtn = document.getElementById('modal-action-btn');
				const titlePrefix = document.getElementById('modal-title-prefix');
				
				modal.setAttribute('data-mode', mode);
				document.getElementById('modal-sku').textContent = sku;
				document.getElementById('image-url-input').value = '';
				document.getElementById('shineon-progress-text').textContent = '';
				document.getElementById('modal-loading').style.display = 'none';
				
				if (mode === 'import') {
					titlePrefix.textContent = 'Import';
					actionBtn.textContent = 'Import + Images';
					document.getElementById('no-artwork-wrapper').style.display = 'block';
					document.getElementById('modal-instruction-text').textContent = 'Enter an image URL and click "Import + Images" to start the process, if applicable.';
					document.getElementById('renders-gallery').innerHTML = '';
				} else {
					titlePrefix.textContent = 'Renders';
					actionBtn.textContent = 'Generate Renders';
					document.getElementById('no-artwork-wrapper').style.display = 'none';
					document.getElementById('no-artwork-checkbox').checked = false;
					document.getElementById('image-url-input').disabled = false;
					document.getElementById('modal-instruction-text').textContent = 'Enter an image URL and click "Generate Renders" to see mockup previews.';
					document.getElementById('renders-gallery').innerHTML = '<p style="color: #999; text-align: center;">Click "Generate Renders" to load renders</p>';
				}
				
				// Store template ID in modal for later use
				modal.setAttribute('data-template-id', templateId);
				modal.classList.add('show');
			}

			function handleModalAction() {
				const mode = document.getElementById('renders-modal').getAttribute('data-mode');
				if (mode === 'import') {
					startShineOnImport();
				} else {
					generateRenders();
				}
			}

			function startShineOnImport() {
				const imageUrlInput = document.getElementById('image-url-input');
				const noArtwork = document.getElementById('no-artwork-checkbox').checked;
				const imageUrl = noArtwork ? '' : imageUrlInput.value.trim();
				
				if (!noArtwork && !imageUrl) {
					alert('Please enter an image URL or check the "No artwork" box.');
					return;
				}

				const actionBtn = document.getElementById('modal-action-btn');
				actionBtn.disabled = true;
				actionBtn.textContent = 'Processing...';

				// This will be defined in class-shineon-integration.php
				if (typeof shineonImportProductWithImages === 'function') {
					shineonImportProductWithImages(imageUrl);
				} else {
					alert('Import function not found.');
				}
			}

			function generateRenders() {
				const modal = document.getElementById('renders-modal');
				const templateId = modal.getAttribute('data-template-id');
				const imageUrl = document.getElementById('image-url-input').value.trim();

				if (!imageUrl) {
					alert('Please enter an image URL');
					return;
				}

				if (!templateId) {
					alert('Template ID not found');
					return;
				}

				document.getElementById('modal-loading').style.display = 'block';
				document.getElementById('renders-gallery').innerHTML = '';

				// Fetch render IDs from backend
				fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: 'action=shineon_generate_renders&template_id=' + templateId + '&image_url=' + encodeURIComponent(imageUrl) + '&nonce=<?php echo esc_attr( wp_create_nonce( 'shineon_nonce' ) ); ?>'
				})
				.then(response => response.json())
				.then(data => {
					if (data.success && data.data.render_ids) {
						const renderIds = data.data.render_ids;
						document.getElementById('modal-loading').style.display = 'none';
						makeParallelRenderRequests(renderIds, imageUrl);
					} else {
						document.getElementById('modal-loading').style.display = 'none';
						document.getElementById('renders-gallery').innerHTML = '<p style="color: #d00; text-align: center;">Error: ' + (data.data || 'Unknown error') + '</p>';
					}
				})
				.catch(error => {
					document.getElementById('modal-loading').style.display = 'none';
					document.getElementById('renders-gallery').innerHTML = '<p style="color: #d00; text-align: center;">Error fetching render IDs.</p>';
					console.error('Error:', error);
				});
			}

			function makeParallelRenderRequests(renderIds, imageUrl) {
				const gallery = document.getElementById('renders-gallery');
				gallery.innerHTML = '';
				let firstLoaded = false;
				const loadingEl = document.getElementById('modal-loading');

				// Create placeholder items for each render
				const items = {};
				renderIds.forEach(renderId => {
					const item = document.createElement('div');
					item.className = 'render-item';
					item.id = 'render-' + renderId;
					item.innerHTML = '<div style="width:200px;height:200px;display:flex;align-items:center;justify-content:center;color:#999;font-size:12px;">Loading...</div>';
					gallery.appendChild(item);
					items[renderId] = item;
				});

				const ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
				const nonce = '<?php echo esc_attr( wp_create_nonce( 'shineon_nonce' ) ); ?>';

				const promises = renderIds.map(renderId => {
					const formData = new URLSearchParams();
					formData.append('action', 'shineon_make_render');
					formData.append('nonce', nonce);
					formData.append('render_id', renderId);
					formData.append('image_url', imageUrl);

					return fetch(ajaxUrl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: formData
					})
					.then(response => response.json())
					.then(result => {
						if (result.success && result.data.url) {
							const itemElement = items[renderId];
							const img = document.createElement('img');
							img.alt = 'Render ' + renderId;
							img.onerror = function() {
								this.parentElement.innerHTML = '<div style="width:200px;height:200px;display:flex;align-items:center;justify-content:center;color:#999;font-size:12px;">Failed to load</div>';
							};
							img.onload = function() {
								if (!firstLoaded) {
									firstLoaded = true;
									loadingEl.style.display = 'none';
								}
							};
							img.src = escapeHtml(result.data.url);
							itemElement.innerHTML = '';
							itemElement.appendChild(img);
							const idDiv = document.createElement('div');
							idDiv.className = 'render-item-id';
							idDiv.textContent = 'Render ID: ' + renderId;
							itemElement.appendChild(idDiv);
						} else {
							items[renderId].innerHTML = '<div style="width:200px;height:200px;display:flex;align-items:center;justify-content:center;color:#d00;font-size:12px;">Failed</div><div class="render-item-id">Render ID: ' + renderId + '</div>';
						}
					})
					.catch(error => {
						items[renderId].innerHTML = '<div style="width:200px;height:200px;display:flex;align-items:center;justify-content:center;color:#d00;font-size:12px;">Error</div><div class="render-item-id">Render ID: ' + renderId + '</div>';
						console.error('Error rendering ' + renderId + ':', error);
					});
				});
			}

			function closeRendersModal() {
				const modal = document.getElementById('renders-modal');
				modal.classList.remove('show');
			}

			function escapeHtml(text) {
				const div = document.createElement('div');
				div.textContent = text;
				return div.innerHTML;
			}

			// Close modal when clicking outside
			document.getElementById('renders-modal').addEventListener('click', function(event) {
				if (event.target === this) {
					closeRendersModal();
				}
			});
		</script>
		<?php
	}
}
