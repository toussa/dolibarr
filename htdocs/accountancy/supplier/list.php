<?php
/* Copyright (C) 2013-2014	Olivier Geffroy			<jeff@jeffinfo.com>
 * Copyright (C) 2013-2016	Alexandre Spangaro		<aspangaro.dolibarr@gmail.com>
 * Copyright (C) 2014-2015	Ari Elbaz (elarifr)		<github@accedinfo.com>
 * Copyright (C) 2013-2014	Florian Henry			<florian.henry@open-concept.pro>
 * Copyright (C) 2014		Juanjo Menent			<jmenent@2byte.es>s
 * Copyright (C) 2016	  	Laurent Destailleur     <eldy@users.sourceforge.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file 		htdocs/accountancy/supplier/list.php
 * \ingroup 	Advanced accountancy
 * \brief 		Ventilation page from suppliers invoices
 */
require '../../main.inc.php';

// Class
require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.product.class.php';
require_once DOL_DOCUMENT_ROOT . '/accountancy/class/html.formventilation.class.php';
require_once DOL_DOCUMENT_ROOT . '/accountancy/class/accountingaccount.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/accounting.lib.php';

// Langs
$langs->load("compta");
$langs->load("bills");
$langs->load("other");
$langs->load("main");
$langs->load("accountancy");

$action = GETPOST('action');

// Select Box
$mesCasesCochees = GETPOST('mesCasesCochees', 'array');

// Search Getpost
$search_invoice = GETPOST('search_invoice', 'alpha');
$search_ref = GETPOST('search_ref', 'alpha');
$search_label = GETPOST('search_label', 'alpha');
$search_desc = GETPOST('search_desc', 'alpha');
$search_amount = GETPOST('search_amount', 'alpha');
$search_account = GETPOST('search_account', 'alpha');
$search_vat = GETPOST('search_vat', 'alpha');
$btn_ventil = GETPOST('ventil', 'alpha');

// Load variable for pagination
$limit = GETPOST('limit') ? GETPOST('limit', 'int') : (empty($conf->global->ACCOUNTING_LIMIT_LIST_VENTILATION)?$conf->liste_limit:$conf->global->ACCOUNTING_LIMIT_LIST_VENTILATION);
$sortfield = GETPOST('sortfield', 'alpha');
$sortorder = GETPOST('sortorder', 'alpha');
$page = GETPOST('page','int');
if ($page < 0) { $page = 0; }
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (! $sortfield)
	$sortfield = "f.datef, f.ref, l.rowid";
if (! $sortorder) {
	if ($conf->global->ACCOUNTING_LIST_SORT_VENTILATION_TODO > 0) {
		$sortorder = "DESC";
	}
}

// Security check
if ($user->societe_id > 0)
	accessforbidden();
if (! $user->rights->accounting->bind->write)
	accessforbidden();

$formventilation = new FormVentilation($db);

// Defaut AccountingAccount RowId Product / Service
// at this time ACCOUNTING_SERVICE_SOLD_ACCOUNT & ACCOUNTING_PRODUCT_SOLD_ACCOUNT are account number not accountingacount rowid
// so we need to get those default value rowid first
$accounting = new AccountingAccount($db);

// TODO: we should need to check if result is a really exist accountaccount rowid.....
$aarowid_s = $accounting->fetch('', $conf->global->ACCOUNTING_SERVICE_BUY_ACCOUNT, 1);
$aarowid_p = $accounting->fetch('', $conf->global->ACCOUNTING_PRODUCT_BUY_ACCOUNT, 1);


/*
 * Action
 */

// Purge search criteria
if (GETPOST("button_removefilter_x") || GETPOST("button_removefilter.x") || GETPOST("button_removefilter")) // All test are required to be compatible with all browsers
{
    $search_ref = '';
    $search_label = '';
    $search_desc = '';
    $search_amount = '';
    $search_account = '';
    $search_vat = '';
}

if ($action == 'ventil' && ! empty($btn_ventil)) {
    $msg='';
    //print '<div><font color="red">' . $langs->trans("Processing") . '...</font></div>';
    if (! empty($mesCasesCochees)) {
        $msg = '<div>' . $langs->trans("SelectedLines") . ': '.count($_POST["mesCasesCochees"]).'</div>';
        $msg.='<div class="detail">';
        $mesCodesVentilChoisis = $codeventil;
        $cpt = 0;
        $ok=0;
        $ko=0;

        foreach ( $mesCasesCochees as $maLigneCochee ) {
            // print '<div><font color="red">id selectionnee : '.$monChoix."</font></div>";
            $maLigneCourante = explode("_", $maLigneCochee);
            $monId = $maLigneCourante[0];
            $monCompte = GETPOST('codeventil'.$monId);

            if ($monCompte <= 0)
            {
                $msg.= '<div><font color="red">' . $langs->trans("Lineofinvoice") . ' ' . $monId . ' - ' . $langs->trans("NoAccountSelected") . '</font></div>';
                $ko++;
            }
            else
            {
                $sql = " UPDATE " . MAIN_DB_PREFIX . "facture_fourn_det";
                $sql .= " SET fk_code_ventilation = " . $monCompte;
                $sql .= " WHERE rowid = " . $monId;
    
                $accountventilated = new AccountingAccount($db);
                $accountventilated->fetch($monCompte, '');
    
                dol_syslog('accountancy/supplier/list.php:: sql=' . $sql, LOG_DEBUG);
                if ($db->query($sql)) {
                    $ok++;
                    $msg.= '<div><font color="green">' . $langs->trans("Lineofinvoice") . ' ' . $monId . ' - ' . $langs->trans("VentilatedinAccount") . ' : ' . length_accountg($accountventilated->account_number) . '</font></div>';
                } else {
                    $ko++;
                    $msg.= '<div><font color="red">' . $langs->trans("ErrorDB") . ' : ' . $langs->trans("Lineofinvoice") . ' ' . $monId . ' - ' . $langs->trans("NotVentilatedinAccount") . ' : ' . length_accountg($accountventilated->account_number) . '<br/> <pre>' . $sql . '</pre></font></div>';
                }
            }
            
            $cpt++;
        }
        $msg.='</div>';
    } else {
        setEventMessages($langs->trans("NoRecordSelected"), null, 'warnings');
    }
    $msg.= '<div>' . $langs->trans("EndProcessing") . '</div>';
}



/*
 * View
 */
llxHeader('', $langs->trans("SuppliersVentilation"));

// Supplier Invoice Lines
$sql = "SELECT f.ref, f.rowid as facid, f.ref_supplier, f.datef, l.fk_product, l.description, l.total_ht as price, l.rowid, l.fk_code_ventilation, l.product_type as type_l, l.tva_tx as tva_tx_line, ";
$sql .= " p.rowid as product_id, p.ref as product_ref, p.label as product_label, p.fk_product_type as type, p.accountancy_code_buy as code_buy, p.tva_tx as tva_tx_prod";
$sql .= " , aa.rowid as aarowid";
$sql .= " FROM " . MAIN_DB_PREFIX . "facture_fourn as f";
$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "facture_fourn_det as l ON f.rowid = l.fk_facture_fourn";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "product as p ON p.rowid = l.fk_product";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "accounting_account as aa ON p.accountancy_code_buy = aa.account_number";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "accounting_system as accsys ON accsys.pcg_version = aa.fk_pcg_version";
$sql .= " WHERE f.fk_statut > 0 AND l.fk_code_ventilation <= 0";
$sql .= " AND product_type <= 2";
$sql .= " AND (accsys.rowid='" . $conf->global->CHARTOFACCOUNTS . "' OR p.accountancy_code_buy IS NULL OR p.accountancy_code_buy ='')";
// Add search filter like
if (strlen(trim($search_invoice))) {
	$sql .= " AND (f.ref like '%" . $search_invoice . "%')";
}
if (strlen(trim($search_ref))) {
	$sql .= " AND (p.ref like '%" . $search_ref . "%')";
}
if (strlen(trim($search_label))) {
	$sql .= " AND (p.label like '%" . $search_label . "%')";
}
if (strlen(trim($search_desc))) {
	$sql .= " AND (l.description like '%" . $search_desc . "%')";
}
if (strlen(trim($search_amount))) {
	$sql .= " AND l.total_ht like '" . $search_amount . "%'";
}
if (strlen(trim($search_account))) {
	$sql .= " AND aa.account_number like '%" . $search_account . "%'";
}
if (strlen(trim($search_vat))) {
	$sql .= " AND (l.tva_tx like '" . $search_vat . "%')";
}
$sql .= " AND f.entity IN (" . getEntity("facture_fourn", 0) . ")";  // We don't share object for accountancy

$sql .= $db->order($sortfield, $sortorder);

// Count total nb of records
$nbtotalofrecords = 0;
if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST))
{
    $result = $db->query($sql);
    $nbtotalofrecords = $db->num_rows($result);
}

$sql .= $db->plimit($limit + 1, $offset);

dol_syslog('accountancy/supplier/list.php');
$result = $db->query($sql);
if ($result) {
	$num_lines = $db->num_rows($result);
	$i = 0;

	$arrayofselected=is_array($toselect)?$toselect:array();
	
	$param='';
	if (! empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) $param.='&contextpage='.$contextpage;
	if ($limit > 0 && $limit != $conf->liste_limit) $param.='&limit='.$limit;
	
	print '<form action="' . $_SERVER["PHP_SELF"] . '" method="post">' . "\n";
	print '<input type="hidden" name="action" value="ventil">';
	if ($optioncss != '') print '<input type="hidden" name="optioncss" value="'.$optioncss.'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
	print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
	print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
	
	$center='<div align="center"><input type="submit" class="butAction" value="' . $langs->trans("Ventilate") . '" name="ventil"></div>';
	
	print_barre_liste($langs->trans("InvoiceLines"), $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $center, $num_lines, 0, 'title_accountancy', 0, '', '', $limit);

	if ($msg) print $msg.'<br>';
	
	print $langs->trans("DescVentilTodoCustomer") . '</br><br>';

    $moreforfilter = '';
	
    print '<table class="tagtable liste'.($moreforfilter?" listwithfilterbefore":"").'">'."\n";
	print '<tr class="liste_titre">';
	print_liste_field_titre($langs->trans("Invoice"), $_SERVER["PHP_SELF"], "f.ref", "", $param, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("Ref"), $_SERVER["PHP_SELF"], "p.ref", "", $param, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("Label"), $_SERVER["PHP_SELF"], "p.label", "", $param, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("Description"), $_SERVER["PHP_SELF"], "l.description", "", $param, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("Amount"), $_SERVER["PHP_SELF"], "l.total_ht", "", $param, 'align="right"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("VATRate"), $_SERVER["PHP_SELF"], "l.tva_tx", "", $param, 'align="right"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("AccountAccountingSuggest"), '', '', '', '', 'align="center"');
	print_liste_field_titre($langs->trans("IntoAccount"), '', '', '', '', 'align="center"');
	print_liste_field_titre($langs->trans("LineId"), $_SERVER["PHP_SELF"], "l.rowid", "", $param, 'align="right"', $sortfield, $sortorder);
	print_liste_field_titre('', '', '', '', '', 'align="center"');
	print "</tr>\n";

	print '<tr class="liste_titre">';
	print '<td class="liste_titre"><input type="text" class="flat" size="10" name="search_invoice" value="' . $search_invoice . '"></td>';
	print '<td class="liste_titre"><input type="text" class="flat" size="10" name="search_ref" value="' . $search_ref . '"></td>';
	print '<td class="liste_titre"><input type="text" class="flat" size="10" name="search_label" value="' . $search_label . '"></td>';
	print '<td class="liste_titre"><input type="text" class="flat" size="10" name="search_desc" value="' . $search_desc . '"></td>';
	print '<td class="liste_titre" align="right"><input type="text" class="flat" size="6" name="search_amount" value="' . $search_amount . '"></td>';
	print '<td class="liste_titre" align="right"><input type="text" class="flat" size="5" name="search_vat" value="' . $search_vat . '"></td>';
	print '<td class="liste_titre"></td>';
	print '<td class="liste_titre"></td>';
	print '<td class="liste_titre"></td>';
	print '<td align="right" class="liste_titre">';
	$searchpitco=$form->showFilterAndCheckAddButtons(1);
	print $searchpitco;
	print '</td>';
	print '</tr>';

	$facturefourn_static = new FactureFournisseur($db);
	$productfourn_static = new ProductFournisseur($db);
	$form = new Form($db);

	$var = True;
	while ( $i < min($num_lines, $limit) ) {
		$objp = $db->fetch_object($result);
		$var = ! $var;

		// product_type: 0 = service ? 1 = product
		// if product does not exist we use the value of product_type provided in facturedet to define if this is a product or service
		// issue : if we change product_type value in product DB it should differ from the value stored in facturedet DB !
		$objp->code_buy_l = '';
		$objp->code_buy_p = '';
		$objp->aarowid_suggest = '';
		$code_buy_p_l_differ = '';

		$code_buy_p_notset = '';

		$objp->aarowid_suggest = $objp->aarowid;
		if (! empty($objp->code_buy)) {
			$objp->code_buy_p = $objp->code_buy;
		} else {
			$code_buy_p_notset = 'color:red';
			if ($objp->type == 1) {
				$objp->code_buy_p = (! empty($conf->global->ACCOUNTING_SERVICE_BUY_ACCOUNT) ? $conf->global->ACCOUNTING_SERVICE_BUY_ACCOUNT : $langs->trans("CodeNotDef"));
			} 

			elseif ($objp->type == 0) {
				$objp->code_buy_p = (! empty($conf->global->ACCOUNTING_PRODUCT_BUY_ACCOUNT) ? $conf->global->ACCOUNTING_PRODUCT_BUY_ACCOUNT : $langs->trans("CodeNotDef"));
			}
		}

		if ($objp->type_l == 1) {
			$objp->code_buy_l = (! empty($conf->global->ACCOUNTING_SERVICE_BUY_ACCOUNT) ? $conf->global->ACCOUNTING_SERVICE_BUY_ACCOUNT : $langs->trans("CodeNotDef"));
			if ($objp->aarowid == '')
				$objp->aarowid_suggest = $aarowid_s;
		} elseif ($objp->type_l == 0) {
			$objp->code_buy_l = (! empty($conf->global->ACCOUNTING_PRODUCT_BUY_ACCOUNT) ? $conf->global->ACCOUNTING_PRODUCT_BUY_ACCOUNT : $langs->trans("CodeNotDef"));
			if ($objp->aarowid == '')
				$objp->aarowid_suggest = $aarowid_p;
		}

		if ($objp->code_buy_l != $objp->code_buy_p)
			$code_buy_p_l_differ = 'color:red';
		
		print '<tr '. $bc[$var].'>';

		// Ref Invoice
		$facturefourn_static->ref = $objp->ref;
		$facturefourn_static->id = $objp->facid;
		print '<td>' . $facturefourn_static->getNomUrl(1) . '</td>';

		// Ref Supplier Invoice
		$productfourn_static->ref = $objp->product_ref;
		$productfourn_static->id = $objp->product_id;
		$productfourn_static->type = $objp->type;
		print '<td>';
		if ($productfourn_static->id)
			print $productfourn_static->getNomUrl(1);
		else
			print '&nbsp;';
		print '</td>';

		print '<td style="' . $code_buy_p_l_differ . '">' . dol_trunc($objp->product_label, 24) . '</td>';

		// TODO: we should set a user defined value to adjust user square / wide screen size
		$trunclength = defined('ACCOUNTING_LENGTH_DESCRIPTION') ? ACCOUNTING_LENGTH_DESCRIPTION : 32;
		print '<td style="' . $code_buy_p_l_differ . '">' . nl2br(dol_trunc($objp->description, $trunclength)) . '</td>';

		print '<td align="right">';
		print price($objp->price);
		print '</td>';

		// Vat rate
		if ($objp->vat_tx_l != $objp->vat_tx_p)
			$code_vat_differ = 'font-weight:bold; text-decoration:blink; color:red';
		print '<td style="' . $code_vat_differ . '" align="right">';
		print price($objp->tva_tx_line);
		print '</td>';

		// Accounting account suggested
		print '<td align="center" style="' . $code_buy_p_notset . '">';
		if ($objp->code_buy_l == $objp->code_buy_p) {               // Test if there is a difference between code by default and code on product
		    if ($objp->code_buy_l > 0) print $objp->code_buy_l;
		    else print $langs->trans("Unknown");
		} else {
		    print $langs->trans("Default") . ' = ' . ($objp->code_buy_l > 0 ? $objp->code_buy_l : $langs->trans("Unknown"));
		    print '<br>';
		    print $langs->trans("Product") . ' = ' . ($objp->code_buy_p > 0 ? $objp->code_buy_p : $langs->trans("Unknown"));
		}
		print '</td>';

		// Colonne choix du compte
		print '<td align="center">';
		print $formventilation->select_account($objp->aarowid_suggest, 'codeventil'.$objp->rowid, 1);
		print '</td>';
		
		// Line id
		print '<td align="center">' . $objp->rowid . '</td>';
		
		// Colonne choix ligne a ventiler
		print '<td align="right">';
		print '<input type="checkbox" class="checkforaction" name="mesCasesCochees[]" value="' . $objp->rowid . "_" . $i . '"' . ($objp->aarowid ? "checked" : "") . '/>';
		print '</td>';

		print "</tr>";
		$i ++;
	}

	print '</table>';
	print '</form>';
} else {
	print $db->error();
}

llxFooter();
$db->close();
