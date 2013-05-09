<?php
App::uses('Component', 'Controller');
/**
 * @author Michal Turek <asgraf@gmail.com>
 * @link https://github.com/Asgraf/AsgrafSearch
 */
class SearchComponent extends Component {
	/**
	 * @var Controller
	 */
	private $controller = null;
	
	public function initialize(Controller $controller) {
		$this->controller = $controller;
	}
	
	public function getSearchConditions() {
		$conditions = array();
		$modelClass = $this->controller->modelClass;
		$plugin = $this->controller->plugin?$this->controller->plugin.'.':null;
		if(empty($this->controller->uses) || !in_array($plugin.$modelClass,$this->controller->uses)) return array();
		/**
		 * @var AppModel $Model
		 */
		$Model = $this->controller->$modelClass;
		$schema = $Model->schema();
		$q = $this->controller->request->query('q');
		foreach(array_keys($this->controller->$modelClass->validate) as $fieldname) {
			if(in_array($fieldname,array('id','password'))) continue;
			$conditionField = $modelClass.'.'.$fieldname;
			$fieldValue = $this->controller->request->query($fieldname);
			if($fieldValue!==null && $fieldValue!=='') {
				if(is_array($fieldValue)) {
					$conditions[]['OR'][$conditionField]=$fieldValue;
				} elseif(in_array($schema[$fieldname]['type'],array('string','text'))) {
					$conditions[$conditionField.' LIKE']='%'.$fieldValue.'%';
				} else {
					$conditions[$conditionField]=$fieldValue;
				}
				continue;
			}
			if($q!==null && $q!=='') {
				if(in_array($schema[$fieldname]['type'],array('string','text','date','time','datetime'))) {
					$conditions['OR'][$conditionField.' LIKE']='%'.$q.'%';
				} elseif($q>0) {
					$conditions['OR'][$conditionField]=$q;
				}
			}
		}
		$ids = array();
		foreach($this->controller->$modelClass->hasAndBelongsToMany as $alias=>$assocData) {
			$fieldValue = $this->controller->request->query($alias);
			if($fieldValue!==null && $fieldValue!=='') {
                list($widthplugin,$widthmodelname) = pluginSplit($assocData['with']);
                if(empty($Model->$widthmodelname)) throw new InternalErrorException(__('%s model has invalid relation with %s model',$Model->alias,$assocData['with']));
				$ids[]=$Model->$widthmodelname->find('list',array('fields'=>$widthmodelname.'.'.$assocData['foreignKey'],'conditions'=>array_merge(array($widthmodelname.'.'.$assocData['associationForeignKey']=>$fieldValue), $assocData['conditions']?:array())));
			}
		}
		switch(count($ids)) {
			case 0:
			break;
		
			case 1:
				$conditions[$modelClass.'.'.$this->controller->$modelClass->primaryKey]=$ids[0];
			break;
			
			default:
				$conditions[$modelClass.'.'.$this->controller->$modelClass->primaryKey]=call_user_func_array('array_intersect',$ids);
			break;
		}

		return $conditions;
	}
} 
