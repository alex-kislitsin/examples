<?php


namespace frontend\widgets;

use kartik\select2\Select2;
use common\models\Filter;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

class FiltersMobileWidget extends \yii\base\Widget
{
    public $filters = [];

    public function run()
    {
        return $this->filters();
    }

    protected function filters()
    {
	    $listItems = null;

	    if (!empty($this->filters)){
	    	$parent = null;
	    	$counterFilter = 0;
	    	$selectFilterId = null;

	    	foreach ($this->filters as $key => $filter){
			    if ($counterFilter > 1) continue;//показываем первые два фильтра
			    if (!in_array($filter['type'],array_keys(Filter::$types))) continue;//временная проверка для теста
			    if ($parent != $filter['filter_id']){
				    $parent = $filter['filter_id'];
				    $counterFilter++;

				    if ($filter['type'] == 0){
					    $listItems[] = $this->renderInput($filter);
				    }
				    if ($filter['type'] != 0){
					    $selectArray = [];
					    foreach ($this->filters as $filter2){
						    if ($filter['filter_id'] == $filter2['filter_id']){
							    $selectArray[$filter2['option_id']] = $filter2['option_name'];
						    }
					    }
					    if (!empty($selectArray)){
						    $listItems[] = $this->renderSelect($selectArray, $filter);
					    }
				    }
			    }
		    }

			if (!empty($listItems)) $listItems = implode("\n", $listItems);
	    }
        return $listItems;
    }

    private function renderInput(array $filter)
    {
    	return "<li class='accordion-item' data-accordion-item>
                <a href='#' class='accordion-title'>{$filter['filter_name']}</a>
                <div class='accordion-content' data-tab-content>
                    <div class='flex-container'>
                        <input type='number' class='from-value' placeholder='От' name='CatalogFilter[input_options_ids][{$filter['option_id']}][]' value=''>
                        <input type='number' class='to-value' placeholder='До' name='CatalogFilter[input_options_ids][{$filter['option_id']}][]' value=''>
                    </div>
                </div>
            </li>";
    }

    private function renderSelect(array $selectArray, array $filter)
    {
	    $listItems[] = "<li class='accordion-item' data-accordion-item>";
	    $listItems[] = "<a href='#' class='accordion-title'>{$filter['filter_name']}</a>";
	    $listItems[] = "<div class='accordion-content' data-tab-content>";
	    $listItems[] = "<ul>";

        foreach ($selectArray as $key => $item){
	        $listItems[] = "<li><input name='acc_type' value='{$key}' type='checkbox' id='checkbox{$key}'><label for='checkbox{$key}'>{$item}</label><span class='available-counter'></span></li>";
        }

	    $listItems[] = "</ul>";
	    $listItems[] = "</div>";
	    $listItems[] = "</li>";

	    return implode("", $listItems);
    }
}