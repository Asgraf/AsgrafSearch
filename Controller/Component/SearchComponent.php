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
		$q = $this->controller->request->param('q')?:$this->controller->request->query('q');
		foreach(array_keys($Model->validate) as $fieldname) {
			if(in_array($fieldname,array('id','password'))) continue;
			$conditionField = $modelClass.'.'.$fieldname;
			$fieldValue = $this->controller->request->param($fieldname)?:$this->controller->request->query($fieldname);
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
		foreach(array('belongsTo','hasOne') as $relation) {
			foreach($Model->$relation as $alias=>$assocData) {
				$assocSchema = $Model->$alias->schema();
				foreach(array_keys($Model->$alias->validate) as $fieldname) {
					if(in_array($fieldname,array('id','password'))) continue;
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
		$ids = array();
		foreach($Model->hasMany as $alias=>$assocData) {
			$fieldValue = $this->controller->request->param($alias)?:$this->controller->request->query($alias);
			if($fieldValue!==null && $fieldValue!=='') {
				$ids[]=$Model->$alias->find('list',array('fields'=>$alias.'.'.$assocData['foreignKey'],'conditions'=>array_merge(array($alias.'.'.$Model->$alias->primaryKey=>$fieldValue), $assocData['conditions']?:array())));
			}
			if($q!==null && $q!=='' && $assocData['foreignKey']!='parent_id') {
				$ids[]=$Model->$alias->find('list',array('fields'=>$alias.'.'.$assocData['foreignKey'],'conditions'=>array_merge(array($alias.'.'.$Model->$alias->displayField.' LIKE'=>'%'.$q.'%'), $assocData['conditions']?:array())));
			}
		}

		foreach($Model->hasAndBelongsToMany as $alias=>$assocData) {
			$fieldValue = $this->controller->request->param($alias)?:$this->controller->request->query($alias);
			if($fieldValue!==null && $fieldValue!=='') {
                list($widthplugin,$widthmodelname) = pluginSplit($assocData['with']);
                if(empty($Model->$widthmodelname)) throw new InternalErrorException(__('%s model has invalid relation with %s model',$Model->alias,$assocData['with']));
				$ids[]=$Model->$widthmodelname->find('list',array('fields'=>$widthmodelname.'.'.$assocData['foreignKey'],'conditions'=>array_merge(array($widthmodelname.'.'.$assocData['associationForeignKey']=>$fieldValue), $assocData['conditions']?:array())));
			}
			if($q!==null && $q!=='') {
				list($widthplugin,$widthmodelname) = pluginSplit($assocData['with']);
				if(empty($Model->$widthmodelname)) throw new InternalErrorException(__('%s model has invalid relation with %s model',$Model->alias,$assocData['with']));
				$ids[]=$Model->$widthmodelname->find('list',array('fields'=>$widthmodelname.'.'.$assocData['foreignKey'],'conditions'=>array_merge(array($widthmodelname.'.'.$Model->$widthmodelname->displayField=>$fieldValue), $assocData['conditions']?:array())));
			}

		}
		switch(count($ids)) {
			case 0:
			break;
		
			case 1:
				$conditions[$modelClass.'.'.$Model->primaryKey]=$ids[0];
			break;
			
			default:
				$conditions[$modelClass.'.'.$Model->primaryKey]=call_user_func_array($this->controller->request->query('q')?'array_merge':'array_intersect',$ids);
			break;
		}

		return $conditions;
	}
} 
