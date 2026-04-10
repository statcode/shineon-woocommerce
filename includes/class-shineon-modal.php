<?php
/**
 * ShineOn Modal Handler
 *
 * Handles rendering and functionality of the modal for viewing product renders.
 *
 * @package ShineOn_For_WooCommerce
 */

class ShineOn_Modal {

	/**
	 * Render the renders modal HTML, styles, and scripts.
	 */
	public function render() {
		?>
		<!-- Renders Modal -->
		<div id="renders-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
			<div style="background: white; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; position: relative;">
				<div style="padding: 20px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">
					<h2 style="margin: 0;">Renders for <span id="modal-sku">SKU</span></h2>
					<button onclick="closeRendersModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
				</div>
				<div style="padding: 20px;">
					<p id="modal-content" style="text-align: center; color: #666;">TESTING RENDER</p>
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
		</style>

		<script>
			function openRendersModal(element) {
				const templateId = element.getAttribute('data-template-id');
				const sku = element.getAttribute('data-sku');
				const modal = document.getElementById('renders-modal');
				
				document.getElementById('modal-sku').textContent = sku;
				document.getElementById('modal-content').textContent = 'TESTING RENDER';
				
				modal.classList.add('show');
			}

			function closeRendersModal() {
				const modal = document.getElementById('renders-modal');
				modal.classList.remove('show');
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
