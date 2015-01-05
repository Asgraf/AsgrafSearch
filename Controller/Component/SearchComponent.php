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

	public function getSearchConditions($universalSearchFieldsWhitelist=array(),$advancedSearchFieldsWhitelist=array()) {
		$modelClass = $this->controller->modelClass;
		$conditions = array();
		$plugin = $this->controller->plugin?$this->controller->plugin.'.':null;
		if(empty($this->controller->uses) || !in_array($plugin.$modelClass,$this->controller->uses)) return array();
		/**
		 * @var AppModel $Model
		 */
		$Model = $this->controller->$modelClass;
		$schema = $Model->schema();
		$q = $this->controller->request->param('q')?:$this->controller->request->query('q');
		$searchFieldsWhitelist = ($q!==null && $q!=='')?$universalSearchFieldsWhitelist:$advancedSearchFieldsWhitelist;
		if(empty($searchFieldsWhitelist) || in_array($modelClass,$searchFieldsWhitelist)) {
			$searchFields = array_diff(array_keys($Model->validate),array('id','password'));
		} else {
			$searchFields = Hash::get($searchFieldsWhitelist,$modelClass)?:array();
		}
		$ids = array();
		foreach($searchFields as $fieldname) {
			$conditionField = $modelClass.'.'.$fieldname;
			$fieldValue = $this->controller->request->param($fieldname)?:$this->controller->request->query($fieldname);
			if($fieldValue!==null && $fieldValue!=='') {
				if($fieldname==$Model->primaryKey) {
					$ids[]=(array)$fieldValue;
				} elseif(is_array($fieldValue)) {
					$conditions[]['OR'][$conditionField]=$fieldValue;
				} elseif(in_array($schema[$fieldname]['type'],array('string','text'))) {
					$conditions[$conditionField.' LIKE']='%'.$fieldValue.'%';
				} else {
					$conditions[$conditionField]=$fieldValue;
				}
				continue;
			}
			if($q!==null && $q!=='') {
				if($fieldname==$Model->primaryKey) {
					$ids[]=(array)$q;
				} elseif(in_array($schema[$fieldname]['type'],array('string','text','date','time','datetime'))) {
					$conditions['OR'][$conditionField.' LIKE']='%'.$q.'%';
				} elseif($q>0) {
					$conditions['OR'][$conditionField]=$q;
				}
			}
		}
		foreach(array('belongsTo','hasOne') as $relation) {
			foreach($Model->$relation as $alias=>$assocData) {
				if(empty($searchFieldsWhitelist) || in_array($alias,$searchFieldsWhitelist)) {
					$searchFields = array_diff(array_keys($Model->$alias->validate),array('id','password'));
				} else {
					$searchFields = Hash::get($searchFieldsWhitelist,$alias);
				}
				if(empty($searchFields)) continue;
				$assocSchema = $Model->$alias->schema();
				foreach($searchFields as $fieldname) {
					$conditionField = $alias.'.'.$fieldname;
					$fieldValue = $this->controller->request->param($conditionField)?:$this->controller->request->query($conditionField);
					if($fieldValue!==null && $fieldValue!=='') {
						if(is_array($fieldValue)) {
							$conditions[]['OR'][$conditionField]=$fieldValue;
						} elseif(in_array($assocSchema[$fieldname]['type'],array('string','text'))) {
							$conditions[$conditionField.' LIKE']='%'.$fieldValue.'%';
						} else {
							$conditions[$conditionField]=$fieldValue;
						}
						continue;
					}
					if($q!==null && $q!=='' && $assocData['foreignKey']!='parent_id') {
						if(in_array($assocSchema[$fieldname]['type'],array('string','text','date','time','datetime'))) {
							$conditions['OR'][$conditionField.' LIKE']='%'.$q.'%';
						} elseif($q>0) {
							$conditions['OR'][$conditionField]=$q;
						}
					}
				}
			}
		}
		foreach($Model->hasMany as $alias=>$assocData) {
			$fieldValue = $this->controller->request->param($alias)?:$this->controller->request->query($alias);
			if($fieldValue!==null && $fieldValue!=='' && !is_array($fieldValue)) {
				if(!(!empty($searchFieldsWhitelist) && !in_array($alias,$searchFieldsWhitelist) && !in_array($Model->$alias->primaryKey,Hash::get($searchFieldsWhitelist,$alias)?:array()))) {
					$ids[]=$Model->$alias->find('list',array('fields'=>$alias.'.'.$assocData['foreignKey'],'conditions'=>array_merge(array($alias.'.'.$Model->$alias->primaryKey=>$fieldValue), $assocData['conditions']?:array())));
				}
			}
			if(empty($searchFieldsWhitelist) && $q!==null && $q!=='' && $assocData['foreignKey']!='parent_id') {
				$ids[]=$Model->$alias->find('list',array('fields'=>$alias.'.'.$assocData['foreignKey'],'conditions'=>array_merge(array($alias.'.'.$Model->$alias->displayField.' LIKE'=>'%'.$q.'%'), $assocData['conditions']?:array())));
			}
			if(in_array($alias,$searchFieldsWhitelist)) {
				$searchFields = array_diff(array_keys($Model->$alias->validate),array('id','password'));
			} else {
				$searchFields = Hash::get($searchFieldsWhitelist,$alias);
			}
			if(!empty($searchFieldsWhitelist) && !empty($searchFields)) {
				$assocSchema = $Model->$alias->schema();
				$assocConds = array();
				foreach($searchFields as $fieldname) {
					$conditionField = $alias.'.'.$fieldname;
					$fieldValue = $this->controller->request->param($conditionField)?:$this->controller->request->query($conditionField);
					if($fieldValue!==null && $fieldValue!=='') {
						if(is_array($fieldValue)) {
							$assocConds[]['OR'][$conditionField]=$fieldValue;
						} elseif(in_array($assocSchema[$fieldname]['type'],array('string','text'))) {
							$assocConds[$conditionField.' LIKE']='%'.$fieldValue.'%';
						} else {
							$assocConds[$conditionField]=$fieldValue;
						}
						continue;
					}
					if($q!==null && $q!=='' && $assocData['foreignKey']!='parent_id') {
						if(in_array($assocSchema[$fieldname]['type'],array('string','text','date','time','datetime'))) {
							$assocConds['OR'][$conditionField.' LIKE']='%'.$q.'%';
						} elseif($q>0) {
							$assocConds['OR'][$conditionField]=$q;
						}
					}
				}
				if(!empty($assocConds)) {
					$ids[]=$test=$Model->$alias->find('list',array('fields'=>$alias.'.'.$assocData['foreignKey'],'conditions'=>$assocConds));
				}
			}
		}

		foreach($Model->hasAndBelongsToMany as $alias=>$assocData) {
			$fieldValue = $this->controller->request->param($alias)?:$this->controller->request->query($alias);
			if($fieldValue!==null && $fieldValue!=='' && !is_array($fieldValue)) {
				if(!(!empty($searchFieldsWhitelist) && !in_array($alias,$searchFieldsWhitelist) && !in_array($Model->$alias->primaryKey,Hash::get($searchFieldsWhitelist,$alias)?:array()))) {
					list($widthplugin,$widthmodelname) = pluginSplit($assocData['with']);
					if(empty($Model->$widthmodelname)) throw new InternalErrorException(__('%s model has invalid relation with %s model',$Model->alias,$assocData['with']));
					$ids[]=$Model->$widthmodelname->find('list',array('fields'=>$widthmodelname.'.'.$assocData['foreignKey'],'conditions'=>array_merge(array($widthmodelname.'.'.$assocData['associationForeignKey']=>$fieldValue), $assocData['conditions']?:array())));
				}
			}
			if(in_array($alias,$searchFieldsWhitelist)) {
				$searchFields = array_diff(array_keys($Model->$alias->validate),array('id','password'));
			} else {
				$searchFields = Hash::get($searchFieldsWhitelist,$alias);
			}
			if(!empty($searchFieldsWhitelist) && !empty($searchFields)) {
				list($widthplugin,$widthmodelname) = pluginSplit($assocData['with']);
				if(empty($Model->$widthmodelname)) throw new InternalErrorException(__('%s model has invalid relation with %s model',$Model->alias,$assocData['with']));
				$assocSchema = $Model->$alias->schema();
				$assocConds = array();
				foreach($searchFields as $fieldname) {
					$conditionField = $alias.'.'.$fieldname;
					$fieldValue = $this->controller->request->param($conditionField)?:$this->controller->request->query($conditionField);
					if(empty($fieldValue) && $fieldname==$Model->$alias->displayField) $fieldValue =$this->controller->request->param($alias)?:$this->controller->request->query($alias);
					if($fieldValue!==null && $fieldValue!=='') {
						if(is_array($fieldValue)) {
							$assocConds[]['OR'][$conditionField]=$fieldValue;
						} elseif(in_array($assocSchema[$fieldname]['type'],array('string','text'))) {
							$assocConds[$conditionField.' LIKE']='%'.$fieldValue.'%';
						} else {
							$assocConds[$conditionField]=$fieldValue;
						}
						continue;
					}
					if($q!==null && $q!=='' && $assocData['foreignKey']!='parent_id') {
						if(in_array($assocSchema[$fieldname]['type'],array('string','text','date','time','datetime'))) {
							$assocConds['OR'][$conditionField.' LIKE']='%'.$q.'%';
						} elseif($q>0) {
							$assocConds['OR'][$conditionField]=$q;
						}
					}
				}
				if(!empty($assocConds)) {
					$habm_ids=$Model->$alias->find('list',array('fields'=>$alias.'.'.$Model->$alias->primaryKey,'conditions'=>$assocConds));
					$ids[]=$Model->$widthmodelname->find('list',array('fields'=>$widthmodelname.'.'.$assocData['foreignKey'],'conditions'=>array_merge(array($widthmodelname.'.'.$assocData['associationForeignKey']=>$habm_ids), $assocData['conditions']?:array())));
				}
			}
		}

		$conds = array();
		switch(count($ids)) {
			case 0:
			break;
		
			case 1:
				$conds[$modelClass.'.'.$Model->primaryKey]=array_unique($ids[0]);
			break;
			
			default:
				$conds[$modelClass.'.'.$Model->primaryKey]=array_unique(call_user_func_array($this->controller->request->query('q')?'array_merge':'array_intersect',$ids));
			break;
		}
		if(!empty($conds)) {
			if($q!==null && $q!=='') {
				$conditions['OR']=(Hash::get($conditions,'OR')?:array())+$conds;
			} else {
				$conditions+=$conds;
			}
		}

		return $conditions;
	}
} 
