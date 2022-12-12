<?php
namespace App\Helpers;

use App\Models\Services;

/**
 * Class to store the entire group tree
 */
class GroupsTree
{
	var $id = 0;
	var $name = '';

	var $children_groups = array();

	var $counter = 0;

	var $current_id = -1;

/**
 * Initializer
 */
	function GroupTree()
	{
		return;
	}

/**
 * Setup which group id to start from
 */
	function build($id, $account_id)
	{
		if ($this->current_id == $id) {
			return;
		}

		if ($id == 0)
		{
			$this->id = 0;
			$this->name = "None";
		} else {
		    $group = Services::where(['id' => $id, 'end_node' => 0, 'account_id' => $account_id])->first()->toArray();
			$this->id = $group['id'];
			$this->name = $group['name'];
        }

		$this->add_sub_groups($account_id);
	}

/**
 * Find and add subgroups as objects
 */
	function add_sub_groups($account_id)
	{
		/* If primary group sort by id else sort by name */
		if ($this->id == 0) {
            $child_group_q = Services::where(['parent_id' => $this->id, 'end_node' => 0, 'account_id' => $account_id])->OrderBy('name','asc')->get()->toArray();
        } else {
            $child_group_q = Services::where(['parent_id' => $this->id, 'end_node' => 0, 'account_id' => $account_id])->OrderBy('name','asc')->get()->toArray();
		}

		$counter = 0;
        foreach ($child_group_q as $row)
        {
            /* Create new AccountList object */
            $this->children_groups[$counter] = new GroupsTree();

            /* Initial setup */
            $this->children_groups[$counter]->current_id = $this->current_id;

            $this->children_groups[$counter]->build($row['id'], $account_id);

            $counter++;
        }
	}

	var $groupList = array();

	/* Convert group tree to a list */
	public function toList($tree, $c = 0)
	{
		$counter = $c;

		if ($tree->id != 0) {
			$this->groupList[$tree->id] = $this->space($counter) . $tree->name;
		}

		/* Process child groups recursively */
		if(count($tree->children_groups) > 0) {
            foreach ($tree->children_groups as $id => $data) {
                $counter++;
                $this->toList($data, $counter);
                $counter--;
            }
        }
	}

    var $groupListView = array();

	/* Convert group tree for List View */
	public function toListView($tree, $c = 0)
	{
		$counter = $c;

		if ($tree->id != 0) {
			$this->groupListView[$tree->id] = array(
			    'id' => $tree->id,
			    'name' => $this->space($counter) . $tree->name,
            );
		}

		/* Process child groups recursively */
		if(count($tree->children_groups) > 0) {
            foreach ($tree->children_groups as $id => $data) {
                $counter++;
                $this->toListView($data, $counter);
                $counter--;
            }
        }
	}

    var $groupListIDs = array();

	/* Convert group tree to array */
	public function toListArray($tree, $c = 0)
	{
		$counter = $c;

		if ($tree->id != 0) {
			$this->groupListIDs[$tree->id] = $tree->id;
		}

		/* Process child groups recursively */
		if(count($tree->children_groups) > 0) {
            foreach ($tree->children_groups as $id => $data) {
                $counter++;
                $this->toListArray($data, $counter);
                $counter--;
            }
        }
	}

	function space($count)
	{
        $str = '';
        for ($i = 1; $i <= $count; $i++) {
            $str .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
		}
		return $str;
	}

    /*
     * Generate Tree from Array.
     *
     * @param Array $data
     * @param $parent
     */
    public static function buildTree(Array $data, $parent = 0, $skip_id = 0) {
        $tree = array();
        foreach ($data as $d) {
            if ($d['parent_id'] == $parent) {
                if($skip_id == $d['id']) {
                    continue;
                }
                $children = self::buildTree($data, $d['id'], $skip_id);
                // set a trivial key
                if (!empty($children)) {
                    $d['children'] = $children;
                }
                $tree[] = $d;
            }
        }
        return $tree;
    }

    /*
     * Generate Dropdown Options from Array.
     *
     * @param Array $arr
     * @param $target
     * @param $parent
     * @return $html
     */
    public static function buildOptions(Array $arr, $target, $parent = NULL) {
        $html = "";
        foreach ( $arr as $key => $v )
        {
            if ( $v['id'] == $target )
//                $html .= "<option value='" . $v['id'] . "' selected='selected'>$parent {$v['name']}</option>\n";
                $html .= "<option value='" . $v['id'] . "' selected='selected'>$parent {$v['name']}</option>";
            else
//                $html .= "<option value='" . $v['id'] . "'>$parent {$v['name']}</option>\n";
                $html .= "<option value='" . $v['id'] . "'>$parent {$v['name']}</option>";

            if (array_key_exists('children', $v))
//                $html .= $this->buildOptions($v['children'],$target,$parent . $v['name']. " - ");
                $html .= self::buildOptions($v['children'],$target,$parent . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . "");
        }

        return $html;
    }

    /*
     * Generate Dropdown Options from Array.
     *
     * @param Array $arr
     * @param $target
     * @param $parent
     * @return $html
     */
    public static function buildArray(Array $arr, $target, $parent = NULL) {
        $html = "";
        foreach ( $arr as $key => $v )
        {
            if ( $v['id'] == $target )
//                $html .= "<option value='" . $v['id'] . "' selected='selected'>$parent {$v['name']}</option>\n";
                $html .= "<option value='" . $v['id'] . "' selected='selected'>$parent {$v['name']}</option>";
            else
//                $html .= "<option value='" . $v['id'] . "'>$parent {$v['name']}</option>\n";
                $html .= "<option value='" . $v['id'] . "'>$parent {$v['name']}</option>";

            if (array_key_exists('children', $v))
//                $html .= $this->buildOptions($v['children'],$target,$parent . $v['name']. " - ");
                $html .= self::buildOptions($v['children'],$target,$parent . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . "");
        }

        return $html;
    }
}
