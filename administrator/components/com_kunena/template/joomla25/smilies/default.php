<?php
/**
 * Kunena Component
 * @package Kunena.Administrator.Template
 * @subpackage Smilies
 *
 * @copyright (C) 2008 - 2012 Kunena Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link http://www.kunena.org
 **/
defined ( '_JEXEC' ) or die ();

$document = JFactory::getDocument();
$document->addStyleSheet ( JUri::base(true).'/components/com_kunena/media/css/admin.css' );
if (JFactory::getLanguage()->isRTL()) $document->addStyleSheet ( JUri::base(true).'/components/com_kunena/media/css/admin.rtl.css' );

$paneOptions = array(
		'onActive' => 'function(title, description){
		description.setStyle("display", "block");
		title.addClass("open").removeClass("closed");
}',
		'onBackground' => 'function(title, description){
		description.setStyle("display", "none");
		title.addClass("closed").removeClass("open");
}',
		'startOffset' => 0,  // 0 starts on the first tab, 1 starts the second, etc...
		'useCookie' => true, // this must not be a string. Don't use quotes.
);
?>
<div id="kadmin">
	<div class="kadmin-left"><?php include KPATH_ADMIN.'/template/joomla25/common/menu.php'; ?></div>
	<div class="kadmin-right">
	<div class="kadmin-functitle icon-smilies"><?php echo JText::_('COM_KUNENA_EMOTICONS_EMOTICON_MANAGER'); ?></div>
		<?php
			echo JHtml::_('tabs.start', 'pane', $paneOptions);
			echo JHtml::_('tabs.panel', JText::_('COM_KUNENA_A_EMOTICONS'), 'panel_emoticons');
		?>
		<form action="<?php echo KunenaRoute::_('administrator/index.php?option=com_kunena') ?>" method="post" id="adminForm" name="adminForm">
			<input type="hidden" name="view" value="smilies" />
			<input type="hidden" name="task" value="" />
			<input type="hidden" name="boxchecked" value="0" />
			<input type="hidden" name="limitstart" value="<?php echo intval ( $this->pagination->limitstart ) ?>" />
			<?php echo JHtml::_( 'form.token' ); ?>

			<fieldset id="filter-bar">
				<div class="filter-search fltlft">
					<label class="filter-search-lbl" for="filter_search"><?php echo JText::_('COM_KUNENA_FILTER'); ?>:</label>
					<input type="text" name="filter_search" id="filter_search" value="<?php echo $this->escape($this->state->get('list.search')); ?>" title="<?php echo JText::_('COM_CONTENT_FILTER_SEARCH_DESC'); ?>" />

					<button type="submit" class="btn"><?php echo JText::_('JSEARCH_FILTER_SUBMIT'); ?></button>
					<button type="button" onclick="document.id('filter_search').value='';this.form.submit();"><?php echo JText::_('JSEARCH_FILTER_CLEAR'); ?></button>
				</div>
				<div class="filter-select fltrt">
					<select name="filter_order_Dir" class="inputbox" onchange="this.form.submit()">
						<option value=""><?php echo JText::_('JFIELD_ORDERING_DESC');?></option>
						<?php echo JHtml::_('select.options', $this->sortDirectionOrdering, 'value', 'text', $this->escape ($this->state->get('list.direction')));?>
					</select>
				</div>
				</fieldset>
			<div class="clr"> </div>

			<table class="adminlist table table-striped">
			<thead>
				<tr>
					<th width="5" align="center">#</th>
					<th align="center" width="5"><input type="checkbox" name="toggle" value="" onclick="checkAll(<?php echo count ( $this->items ); ?>);" /></th>
					<th width="5%" class="center"><?php echo JText::_('COM_KUNENA_EMOTICON'); ?></th>
					<th width="8%" class="center"><?php echo JHtml::_('grid.sort', 'COM_KUNENA_EMOTICONS_CODE', 'code', $this->state->get('list.direction'), $this->state->get('list.ordering') ); ?></th>
					<th><?php echo JHtml::_('grid.sort', 'COM_KUNENA_EMOTICONS_URL', 'location', $this->state->get('list.direction'), $this->state->get('list.ordering') ); ?></th>
					<th width="1%">
						<?php echo JHtml::_('grid.sort', 'JGRID_HEADING_ID', 'id', $this->state->get('list.direction'), $this->state->get('list.ordering')); ?>
					</th>
				</tr>
				<tr>
					<td class="center">
					</td>
					<td class="center">
					</td>
					<td class="center">
					</td>
					<td class="nowrap center">
						<label for="filter_code" class="element-invisible"><?php echo JText::_('COM_KUNENA_FIELD_LABEL_SEARCHIN') ?>:</label>
						<input class="input-block-level input-filter filter" type="text" name="filter_code" id="filter_code" placeholder="<?php echo JText::_('JSEARCH_FILTER_LABEL') ?>" value="<?php echo $this->filterCode; ?>" title="<?php echo JText::_('JSEARCH_FILTER_LABEL') ?>" />
					</td>
					<td class="nowrap center">
						<label for="filter_url" class="element-invisible"><?php echo JText::_('COM_KUNENA_FIELD_LABEL_SEARCHIN') ?>:</label>
						<input class="input-block-level input-filter filter" type="text" name="filter_url" id="filter_url" placeholder="<?php echo JText::_('JSEARCH_FILTER_LABEL') ?>" value="<?php echo $this->filterUrl; ?>" title="<?php echo JText::_('JSEARCH_FILTER_LABEL') ?>" />
					</td>
					<td class="center">
					</td>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<td colspan="14">
						<div class="pagination">
							<div class="limit"><?php echo JText::_('COM_KUNENA_A_DISPLAY')?><?php echo $this->pagination->getLimitBox (); ?></div>
							<?php echo $this->pagination->getPagesLinks (); ?>
							<div class="limit"><?php echo $this->pagination->getResultsCounter (); ?></div>
						</div>
					</td>
				</tr>
			</tfoot>
				<?php
					$k = 1;
					$i = 0;
					foreach ( $this->items as $id => $row ) {
						$k = 1 - $k;
				?>
				<tr class="row<?php echo $k; ?>" align="center">
					<td align="center"><?php
						echo ($id + $this->pagination->limitstart + 1);
						?></td>
					<td align="center"><input type="checkbox"
						id="cb<?php
						echo $id;
						?>" name="cid[]"
						value="<?php
						echo $this->escape($row->id);
						?>"
						onclick="isChecked(this.checked);" /></td>
					<td width="50" align="center"><a href="#edit"
						onclick="return listItemTask('cb<?php
						echo $id;
						?>','edit')"><img
						src="<?php
						echo $this->escape( $this->ktemplate->getSmileyPath($row->location, true) )
						?>"
						alt="<?php
						echo $this->escape($row->location);
						?>" border="0" /></a></td>
					<td width="50" align="center"><?php echo $this->escape($row->code); ?>&nbsp;</td>
					<td width="80%" align="left"><?php echo $this->escape($row->location); ?>&nbsp;</td>
					<td class="nowrap center">
							<?php echo $this->escape($row->id); ?>
					</td>
				</tr>
				<?php
					}
					?>
			</table>
		</form>

		<?php echo JHtml::_('tabs.panel', JText::_('COM_KUNENA_A_EMOTICONS_UPLOAD'), 'panel_upload'); ?>

		<form action="<?php echo KunenaRoute::_('administrator/index.php?option=com_kunena') ?>" id="uploadForm" method="post" enctype="multipart/form-data" >
		<input type="hidden" name="view" value="smilies" />
		<input type="hidden" name="task" value="smileyupload" />
		<input type="hidden" name="boxchecked" value="0" />
		<?php echo JHtml::_( 'form.token' ); ?>

		<div style="padding:10px;">
			<input type="file" id="file-upload" name="Filedata" />
			<input type="submit" id="file-upload-submit" value="<?php echo JText::_('COM_KUNENA_A_START_UPLOAD'); ?>" />
			<span id="upload-clear"></span>
		</div>
		<ul class="upload-queue" id="upload-queue">
			<li style="display: none" />
		</ul>
		</form>

		<?php echo JHtml::_('tabs.end'); ?>
	</div>
	<div class="kadmin-footer">
		<?php echo KunenaVersion::getLongVersionHTML (); ?>
	</div>
</div>
