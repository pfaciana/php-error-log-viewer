<?php

namespace ErrorLogViewer;

class ErrorLogViewer
{
	use Traits\HelperTrait;

	public static function get_instance ()
	{
		static $instance;

		if ( !$instance instanceof static ) {
			$instance = new static;
		}

		return $instance;
	}

	protected function __construct ()
	{
		add_action( 'admin_init', fn() => $this->createSettings() );

		add_action( "wp_ajax_delete_in_error_log", fn() => $this->deleteInErrorLog() );
		add_action( "wp_ajax_delete_error_log_file", fn() => $this->deleteErrorLogFile() );

		add_action( 'admin_menu', function () {
			add_menu_page( 'PHP Error Log Viewer', 'Error Log Viewer', 'manage_options', 'php-error-log-viewer', function () {
				$this->displayErrorLogs();
			}, 'dashicons-code-standards' );
		} );
	}

	protected function createSettings ()
	{
		$rwp = RWP();

		$group = $rwp->getSettingGroup( 'elv_settings_group' );

		$setting = $group->addSetting( 'elv_settings' );

		$setting->number( 'directory_depth', [
			'title'             => 'Directory Depth',
			'attr'              => [ 'class' => 'small-text', 'min' => 0, 'step' => 1 ],
			'sanitize_callback' => 'absint',
			'description'       => 'How many levels of subdirectories to include in the search.',
			'default'           => 7,
		] );
		$setting->text( 'exclude_directories', [
			'title'           => 'Exclude Directories',
			'attrs'           => [ 'placeholder' => 'node_modules, vendor' ],
			'filter_callback' => fn( $value ) => empty( $value ) ? [] : array_map( 'trim', explode( ',', $value ) ),
			'description'     => 'Comma separated list of directories to exclude from searching.',
			'after_field'     => '<p>Excluding directories can cut down on processing time and removes false positives.</p>',
			'default'         => 'node_modules, vendor',
		] );
		$setting->text( 'include_filenames', [
			'title'           => 'Include Error Logs',
			'attrs'           => [ 'placeholder' => 'Additional custom error logs' ],
			'filter_callback' => fn( $value ) => empty( $value ) ? [] : array_map( 'trim', explode( ',', $value ) ),
			'description'     => 'Comma separated list of error log <b>filenames to include</b>.',
			'after_field'     => "<p><code>error_log</code>, <code>php_error</code> and <code>debug.log</code> files are already included.
							<br>Typically this can be left empty.</p>",
		] );
		$setting->url( 'file_link_format', [
			'before_field'      => $setting->toggle( 'use_file_link_format', [ 'options' => [ 1 => ' Enable?', ], 'filter_callback' => fn( $value ) => !!$value, ], FALSE )->html(),
			'attrs'             => [ 'placeholder' => 'Link to open file in IDE' ],
			'sanitize_callback' => function ( $value ) {
				$url_components = wp_parse_url( $value );

				if ( empty( $scheme = $url_components['scheme'] ?? NULL ) || empty ( $value = esc_url( $value, [ $scheme ] ) ) ) {
					return '';
				}

				return $value;
			},
			'description'       => 'Example: <code>http://localhost:63342/api/file/%f:%l</code>.',
			'default'           => ini_get( 'xdebug.file_link_format' ) ?: NULL,
		] );
		$setting->text( 'replace_path', [
			'attrs'             => [ 'placeholder' => 'Optional Local Path' ],
			'sanitize_callback' => function ( $value ) {
				if ( empty( $value = trim( $value ) ) ) {
					return NULL;
				}

				return preg_replace( '/[<>"|?*\x00-\x1F\x7F]/', '', trailingslashit( wp_normalize_path( $value ) ) );
			},
			'description'       => "Replaces remote path with your local path in the file link.<br>
								Current Server Path: <code>" . static::getWpConfigDir() . "</code>",
		] );
	}

	protected function deleteInErrorLog ()
	{
		if ( empty( $_POST['error'] ?? FALSE ) ) {
			wp_send_json( FALSE );
		}

		$errorFixed = stripslashes( $_POST['error'] );

		$errorFixed = json_decode( $errorFixed, TRUE );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json( FALSE );
		}

		if ( !is_writeable( $errorFixed['log'] ) ) {
			wp_send_json( FALSE );
		}

		$result = static::removeErrorsFromLogs( $errorFixed );

		wp_send_json( $result );
	}

	protected function deleteErrorLogFile ()
	{
		if ( empty( $_POST['filename'] ?? FALSE ) ) {
			wp_send_json( FALSE );
		}

		$result = @unlink( $_POST['filename'] );

		wp_send_json( $result );
	}

	public function displayErrorLogs ()
	{
		$active_tab = $_GET['tab'] ?? FALSE
		?>
		<style>
			.notice {
				margin-left: 0 !important;
			}
		</style>
		<nav class="nav-tab-wrapper">
			<a href="<?= remove_query_arg( 'tab' ) ?>" class="nav-tab <?= !$active_tab ? 'nav-tab-active' : '' ?>">Error Logs</a>
			<a href="<?= add_query_arg( 'tab', 'settings' ) ?>" class="nav-tab <?= $active_tab === 'settings' ? 'nav-tab-active' : '' ?>">Settings</a>
		</nav>
		<?php if ( !$active_tab ) : ?>
		<div style="padding-right: 10px;">
			<h1>Error Logs</h1>
			<?php
			if ( empty( $directory = static::getWpConfigDir() ) ) {
				return print '<div class="notice notice-error"><p><b>Could not read filesystem. Be sure to check your PHP settings.</b></p></div>';
			}
			static::flush();
			$errorLogs = $this->searchErrorLogs( $directory, RWP()->getOption( 'elv_settings' ) );
			if ( empty( $errorLogs ) ) {
				return print '<div class="notice notice-warning"><p><b>No error logs files were found on you system.</b></p></div>';
			}
			?>
			<p>Searching in: <b><?= $directory ?></b></p>

			<div class="found-error-logs">
				Found:
				<ul style="margin-top:0">
					<?php foreach ( $errorLogs as $errorLog ) : ?>
						<li data-file-path="<?= $errorLog ?>" style="font-weight: 600">
							<?= static::getMaybeFileLink( $errorLog, $errorLog ) ?>
							[<span class="error-file-size"><?= static::getFileSize( $errorLog ) ?></span>]
							<button type="button" class="button-link delete-error-file">🗑️</button>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php
			static::flush();
			$data = $this->getData( $errorLogs );
			?>
			<div id="error_log"></div>
			<script type="application/javascript">
				jQuery(function($) {
					const T = window.Tabulator
					var tableData = <?= json_encode( array_values( $data ) ) ?>;
					var baseDir = '<?=$directory?>'

					var columns = [
						{ title: 'Type', field: 'type', formatter: 'list', cellDblClick: toggleFilter },
						{ title: 'Message', field: 'message', formatter: 'textarea', headerFilter: 'input', hozAlign: 'left', variableHeight: true, cellDblClick: toggleFilter, width: 999 },
						{ title: 'Time', field: 'timestamp', formatter: 'maxTimeAgo' },
						{ title: 'Count', field: 'count', formatter: 'min' },
						{
							title: 'Log', field: 'log', formatter: 'list', cellDblClick: toggleFilter, maxWidth: 575,
							formatterOutput: function(cell, formatterParams, onRendered) {
								return getMaybeFileLink(cell.getValue().replace(baseDir, ''), cell.getValue())
							},
						},
						{
							title: 'File', field: 'file', headerFilter: 'input', hozAlign: 'left', cellDblClick: toggleFilter, maxWidth: 575,
							formatter: function(cell, formatterParams, onRendered) {
								return getMaybeFileLink(`${cell.getValue().replace(baseDir, '')} : ${cell.getData().line || 1}`, cell.getValue(), cell.getData().line || 1)
							},
						},
						{
							title: 'Delete', field: 'delete', tooltip: 'Remove', formatter: () => `❌`,
							cellClick: function(e, cell) {
								cell.getTable().alert(`Removing this ${cell.getRow().getData().type} from the log`)
								$.ajax(window.ajaxurl, {
									method: 'POST',
									data: {
										action: 'delete_in_error_log',
										error: JSON.stringify(cell.getRow().getData()),
									},
									success: function(response) {
										if (response === false) {
											return console.error('Failed to delete row')
										}
										const filename = cell.getRow().getData().log
										const $file = $(`[data-file-path="${filename}"]`)
										if (!response) {
											return console.log('File list item should be deleted!!!')
										}
										$file.find('.error-file-size').text(response)
										cell.getRow().delete()
										console.log('Row deleted successfully')
									},
									error: function(xhr, status, error) {
										console.error('Error deleting row:', error)
									},
									complete: function(jqXHR, textStatus) {
										cell.getTable().clearAlert()
									},
								})
							},
						},
					]

					var table = Tabulator.Create('#error_log', {
						data: tableData,
						columns,
						height: 'auto',
						pagination: 'local',
						paginationSize: 20,
						paginationSizeSelector: [1, 5, 10, 20, 50, 100, true],
						paginationButtonCount: 15,
						layout: 'fitDataFill',
						movableColumns: false,
						movableRows: false,
						footerElement: '<button class="clear-all-table-filters tabulator-page">Clear Filters</button> &nbsp; <button class="clear-all-table-sorting tabulator-page">Clear Sorting</button>',
					})

					table.on('renderComplete', function() {
						setDynamicColumnWidth('message')
						table.rowManager.rows.forEach(row => {
							row.reinitializeHeight()
							if (row.getHeight() > 175) {
								row.setHeight(175)
							}
						})
						setDynamicColumnWidth('message')
					})

					function toggleFilter(e, cell) {
						const column = cell.getColumn()
						const value = cell.getValue()
						column.setHeaderFilterValue(column.getHeaderFilterValue() === value ? '' : value)
					}

					$(document).on('click', `.tabulator .clear-all-table-filters`, function(e) {
						e.preventDefault()
						$(this).closest('.tabulator').each(function() {
							$.each(window.Tabulator.findTable(this), function() {
								this.clearHeaderFilter()
							})
						})
					})

					$(document).on('click', `.tabulator .clear-all-table-sorting`, function(e) {
						e.preventDefault()
						$(this).closest('.tabulator').each(function() {
							$.each(window.Tabulator.findTable(this), function() {
								this.clearSort()
							})
						})
					})

					$(document).on('click', '.ide-link', function(e) {
						e.preventDefault()
						$.ajax($(this).attr('href'))
						return false
					})

					$(document).on('click', '.delete-error-file', function(e) {
						e.preventDefault()
						const $li = $(this).closest('[data-file-path]')
						const size = $li.find('.error-file-size').text()
						if (size !== '0 bytes' && !window.confirm('Log file is not empty, are you sure you want to delete it?')) {
							return
						}
						const $ul = $li.parent()
						const filename = $li.attr('data-file-path')
						table.alert(`Deleting the file log "${filename}"`)
						$.ajax(window.ajaxurl, {
							method: 'POST',
							data: { action: 'delete_error_log_file', filename },
							success: function(response) {
								if (response === false) {
									return console.error('Failed to delete file')
								}
								table.getRows().reverse().forEach(function(row) {
									if (row.getData()?.log === filename) {
										table.deleteRow(row)
									}
								})
								$li.remove()
								if (!$ul.children().length) {
									$('.found-error-logs').html(`<p>All error logs have been successfully deleted!</p>`)
								}
								console.log('File deleted successfully')
							},
							error: function(xhr, status, error) {
								console.error('Error deleting file:', error)
							},
							complete: function(jqXHR, textStatus) {
								table.clearAlert()
							},
						})
					})

					function formatFileLink(file, line = 1) {
						<?php $s = RWP()->getSetting( 'elv_settings' ); ?>
						const file_link_format = '<?php ( function () use ( $s ) {
							if ( empty( $s->getValue( 'use_file_link_format' ) ) || empty( $link = $s->getValue( 'file_link_format' ) ) ) {
								return '';
							}
							echo $link;
						} )() ?>'
						<?php if (!empty( $replace_path = $s->getValue( 'replace_path' ) )) : ?>
						file = file.replace(<?=json_encode( static::getWpConfigDir() )?>, <?=json_encode( $replace_path )?>)
						<?php endif; ?>
						return file_link_format ? file_link_format.replace('%f', encodeURIComponent(file)).replace('%l', line) : null
					}

					function getMaybeFileLink(text, file, line = 1) {
						text = text && text != 0 ? text : file
						const link = formatFileLink(file, line)
						return link ? `<a class="ide-link" href="${link}" target="_blank">${text}</a>` : text
					}

					function setDynamicColumnWidth(field) {
						let tableWidth = table.element.clientWidth
						let dynamicColumn = table.getColumn(field)
						let otherColumnsWidth = 0

						table.getColumns().forEach(column => {
							if (column.getField() !== field) {
								otherColumnsWidth += column.getWidth()
							}
						})

						dynamicColumn.setWidth(Math.max(tableWidth - otherColumnsWidth, 100))
					}
				})
			</script>
		</div>
	<?php elseif ( $active_tab === 'settings' ) : ?>
		<h1>Error Log Viewer Settings</h1>
		<?php
		echo RWP()->getSettingsForm( 'elv_settings_group' );
	endif;

	}
}