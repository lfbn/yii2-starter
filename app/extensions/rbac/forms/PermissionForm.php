<?php

namespace justcoded\yii2\rbac\forms;

use justcoded\yii2\rbac\models\Item;
use yii\rbac\Role;
use Yii;
use yii\helpers\ArrayHelper;


class PermissionForm extends ItemForm
{

	public $parent_roles;
	public $parent_permissions;
	public $children_permissions;

	public $parent_roles_search;
	public $parent_permissions_search;
	public $children_permissions_search;

	/**
	 * @inheritdoc
	 * @return array
	 */
	public function rules()
	{
		return ArrayHelper::merge(parent::rules(), [
			['ruleName', 'match', 'pattern' => '/^[a-z][\w\-\/]*$/i'],
			[['parent_roles', 'parent_permissions', 'children_permissions'], 'string'],
		]);
	}

	/**
	 * @param $attribute
	 * @return bool
	 */
	public function uniqueName($attribute)
	{
		if (Yii::$app->authManager->getPermission($this->getAttributes()['name'])) {
			$this->addError($attribute, 'Name must be unique');

			return false;
		}

		return true;
	}

	/**
	 * @return bool
	 */
	public function beforeValidate()
	{
		$this->type = Role::TYPE_PERMISSION;
		return parent::beforeValidate();
	}


	/**
	 * @return string
	 */
	public function getParentRolesString()
	{
		$roles = $this->findRolesWithChildItem();

		$string_roles = '';
		foreach ($roles as $role_name => $role){
			foreach ($role->data as $child_name => $child){
				if ($child->name == $this->name){
					$string_roles .= $role_name .',';
				}
			}
		}

		return substr($string_roles, 0, -1);
	}


	/**
	 * @return string
	 */
	public function getParentPermissionsString()
	{
		$permissions = $this->findPermissionsWithChildItem();

		$string_permissions = '';
		foreach ($permissions as $name_permissions => $permission){
			foreach ($permission->data as $child_name => $child){
				if ($child->name == $this->name){
					$string_permissions .= $name_permissions .',';
				}
			}
		}

		return substr($string_permissions, 0, -1);
	}


	/**
	 * @return string
	 */
	public function getChildrenPermissionsString()
	{
		$permissions = Yii::$app->authManager->getChildren($this->name);

		return implode(',', array_keys($permissions));
	}

	/**
	 * @return bool
	 */
	public function store()
	{

		if(!$permission = Yii::$app->authManager->getPermission($this->name)){
			$permission = Yii::$app->authManager->createPermission($this->name);
			$permission->description = $this->description;
			if(!Yii::$app->authManager->add($permission)){
				return false;
			}
		}else{
			$permission->description = $this->description;
			Yii::$app->authManager->update($this->name, $permission);
		}

//		if(!empty($data->rule_name)){
//
//			if (!Yii::$app->authManager->getRule($data->rule_name)) {
//
////				$rule = Yii::createObject(['class' => Item::class], $data);
////				pa($rule,1);
//				pa(Yii::$app->authManager->add($data),1 );
//			}
//		}

		$this->storeParentRoles();

		$this->storeParentPermissions();

		$this->storeChildrenPermissions();

		return true;
	}


	/**
	 * @return bool
	 */
	public function storeParentRoles()
	{
		$permission = Yii::$app->authManager->getPermission($this->name);

		$old_parent_roles = $this->getParentRoles();

		$this->removeChildrenArray($old_parent_roles, $permission);

		if (!empty($this->parent_roles)){
			$array_parent_roles = explode(',', $this->parent_roles);
			$this->addChildrenArray($array_parent_roles, ['child' => $permission], false);
		}

		return true;
	}

	/**
	 * @return bool
	 */
	public function storeParentPermissions()
	{
		$permission = Yii::$app->authManager->getPermission($this->name);

		$old_parent_permissions = $this->getParentPermissions();

		$this->removeChildrenArray($old_parent_permissions, $permission);

		if (!empty($this->parent_permissions)){
			$array_parent_permissions = explode(',', $this->parent_permissions);
			$this->addChildrenArray($array_parent_permissions, ['child' => $permission]);
		}

		return true;
	}

	/**
	 * @return bool
	 */
	public function storeChildrenPermissions()
	{
		$parent_permission = Yii::$app->authManager->getPermission($this->name);
		Yii::$app->authManager->removeChildren($parent_permission);

		if (!empty($this->children_permissions)){
			$array_children_permissions = explode(',', $this->children_permissions);

			foreach ($array_children_permissions as $permission) {
				$child_permission = Yii::$app->authManager->getPermission($permission);
				Yii::$app->authManager->addChild($parent_permission, $child_permission);
			}
		}

		return true;
	}

	/**
	 * @return array
	 */
	public function getParentPermissions()
	{
		$permissions = $this->findPermissionsWithChildItem();

		$parent_permissions = [];
		foreach ($permissions as $name_permission => $permission){
			foreach ($permission->data as $child_name => $child){
				if ($child->name == $this->name){
					$parent_permissions[$name_permission] = $permission;
				}
			}
		}

		return $parent_permissions;
	}

	/**
	 * @return array
	 */
	public function getParentRoles()
	{
		$roles = $this->findRolesWithChildItem();

		$parent_roles = [];
		foreach ($roles as $name_role => $role){
			foreach ($role->data as $child_name => $child){
				if ($child->name == $this->name){
					$parent_roles[$name_role] = $role;
				}
			}
		}

		return $parent_roles;
	}

	/**
	 * @return \yii\rbac\Role[]
	 */
	public function findRolesWithChildItem()
	{
		$data = Yii::$app->authManager->getRoles();

		foreach ($data as $role => $value){
			$data[$role]->data = Yii::$app->authManager->getChildren($value->name);
		}

		return $data;
	}

	/**
	 * @return \yii\rbac\Permission[]
	 */
	public function findPermissionsWithChildItem()
	{
		$data = Yii::$app->authManager->getPermissions();

		foreach ($data as $permission => $value){
			$data[$permission]->data = Yii::$app->authManager->getChildren($value->name);
		}

		return $data;
	}

}