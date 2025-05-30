<?php
/* ----------------------------------------------------------------------
 * app/controllers/administrate/maintenance/caUtillsController.php :
 * ----------------------------------------------------------------------
 * Pelhamhs V1.1.3

 * ----------------------------------------------------------------------
 */

require_once(__CA_LIB_DIR__."/Search/SearchEngine.php");
require_once(__CA_LIB_DIR__."/Media.php");
require_once(__CA_LIB_DIR__."/ApplicationPluginManager.php");
require_once(__CA_APP_DIR__."/helpers/configurationHelpers.php");
require_once(__CA_LIB_DIR__."/Search/SearchIndexer.php");
require_once(__CA_LIB_DIR__.'/SearchReindexingProgress.php');
require_once(__CA_LIB_DIR__.'/Utils/BaseApplicationTool.php');

class caUtillsController extends ActionController {

	# ------------------------------------------------	
	public function __construct(&$po_request, &$po_response, $pa_view_paths=null) {
		parent::__construct($po_request, $po_response, $pa_view_paths);
		
		if (!$this->request->isLoggedIn() || !$this->request->user->canDoAction('can_do_search_reindex')) {
			$this->response->setRedirect($this->request->config->get('error_display_url').'/n/2320?r='.urlencode($this->request->getFullUrlPath()));
 			return;
		}	
	}
	# ------------------------------------------------
	public function Index(){
		$this->render('caUtills_landing_html.php');
	}
	# ------------------------------------------------
	public function Reindex(){
		$this->render('caUtills_landing_html.php?');
	}
	# ------------------------------------------------
}
?>