<?php

declare(strict_types=1);
namespace App\Helpers;

use App\Models\Services;

/**
 * Class to store the entire group tree
 */
class NodesTree
{
    public int $id = 0;

    public string $name = '';

    public mixed $parent_id = '';

    public string $slug = '';

    public mixed $complimentory = '';

    public mixed $active = '';

    public mixed $duration = '';

    public mixed $price = '';

    public string $color = '';

    public mixed $end_node = '';

    public bool $non_negative_groups = false;

    public array $children_groups = [];

    public array $children_nodes = [];

    public int $counter = 0;

    public int $current_id = -1;

    public string $default_text = 'Please select...';

    /**
     * Initializer
     */
    public function NodesTree()
    {

    }

    /**
     * Setup which group id to start from
     */
    public function build($id, $account_id, $end_node = true, $only_active = false)
    {
        if ($id == 0) {
            $this->id = 0;
            $this->name = 'None';
            $this->active = 1;
        } else {
            $where = [];
            $where['id'] = $id;
            $where['account_id'] = $account_id;

            if ($end_node) {
                $where['end_node'] = 0;
            }
            if ($only_active) {
                $where['active'] = 1;
            }

            // Guard against the caller having stricter filters than the
            // parent query that produced this id (e.g., add_sub_groups()
            // returns inactive rows but build() is recursed with
            // $only_active=true). Also covers the case where a service was
            // deleted/inactivated between queries. Treat as a skipped node.
            $service = Services::where($where)->first();
            if ($service === null) {
                $this->id = 0;
                $this->name = '';
                $this->active = 0;

                return;
            }
            $group = $service->toArray();

            $this->id = $group['id'];
            $this->name = $group['name'];
            $this->parent_id = $group['parent_id'];
            $this->slug = $group['slug'];
            $this->complimentory = $group['complimentory'];
            $this->active = $group['active'];
            $this->duration = $group['duration'];
            $this->price = $group['price'];
            $this->color = $group['color'];
            $this->end_node = $group['end_node'];
        }
        $this->add_sub_nodes($account_id, $only_active);
        $this->add_sub_groups($account_id, $only_active);
    }

    /**
     * Find and add subgroups as objects
     */
    public function add_sub_groups($account_id, $only_active = false)
    {
        $query = Services::where(['end_node' => 0, 'account_id' => $account_id]);

        if ($this->id == 0) {
            $query->where(function ($q) {
                $q->whereNull('parent_id')->orWhere('parent_id', 0);
            });
        } else {
            $query->where('parent_id', $this->id);
        }

        if ($only_active) {
            $query->where('active', 1);
        }

        $child_group_q = $query->orderBy('name', 'asc')->get()->toArray();
        $counter = 0;
        foreach ($child_group_q as $row) {
            /* Create new AccountList object */
            $this->children_groups[$counter] = new NodesTree();
            /* Initial setup */
            $this->children_groups[$counter]->current_id = $this->current_id;
            $this->children_groups[$counter]->non_negative_groups = $this->non_negative_groups;
            $this->children_groups[$counter]->build($row['id'], $account_id, true, true);
            $counter++;
        }
    }

    /**
     * Find and add subnodes as array items
     */
    public function add_sub_nodes($account_id, $only_active = false)
    {
        $query = Services::where(['end_node' => 1, 'account_id' => $account_id]);

        if ($this->id == 0) {
            $query->where(function ($q) {
                $q->whereNull('parent_id')->orWhere('parent_id', 0);
            });
        } else {
            $query->where('parent_id', $this->id);
        }

        if ($only_active) {
            $query->where('active', 1);
        }

        $child_node_q = $query->orderBy('name', 'asc')->get()->toArray();
        $counter = 0;
        if (count($child_node_q)) {
            foreach ($child_node_q as $row) {
                $this->children_nodes[$counter]['id'] = $row['id'];
                $this->children_nodes[$counter]['name'] = $row['name'];
                $this->children_nodes[$counter]['parent_id'] = $row['parent_id'];
                $this->children_nodes[$counter]['slug'] = $row['slug'];
                $this->children_nodes[$counter]['complimentory'] = $row['complimentory'];
                $this->children_nodes[$counter]['duration'] = $row['duration'];
                $this->children_nodes[$counter]['price'] = $row['price'];
                $this->children_nodes[$counter]['color'] = $row['color'];
                $this->children_nodes[$counter]['end_node'] = $row['end_node'];
                $this->children_nodes[$counter]['active'] = $row['active'];
                $counter++;
            }
        }
    }

    public array $nodeList = [];

    /* Convert node tree to a list */
    public function toList($tree, $c = 0, $active_only = false)
    {
        /* Add group name to list */
        if ($tree->id != 0) {
            if ($this->non_negative_groups) {
                /* Set the group id to negative value since we want to disable it */
                $this->nodeList[$tree->id] = [
                    'id' => $tree->id,
                    'name' => $this->space($c).$tree->name,
                    'parent_id' => $tree->parent_id,
                    'slug' => $tree->slug,
                    'complimentory' => $tree->complimentory,
                    'active' => $tree->active,
                    'duration' => $tree->duration,
                    'price' => $tree->price,
                    'color' => $tree->color,
                    'end_node' => $tree->end_node,
                ];
            } else {
                $this->nodeList[-$tree->id] = [
                    'id' => $tree->id,
                    'name' => $this->space($c).$tree->name,
                    'parent_id' => $tree->parent_id,
                    'slug' => $tree->slug,
                    'complimentory' => $tree->complimentory,
                    'active' => $tree->active,
                    'duration' => $tree->duration,
                    'price' => $tree->price,
                    'color' => $tree->color,
                    'end_node' => $tree->end_node,
                ];
            }
        } else {
            $this->nodeList[0] = $this->default_text;
        }
        /* Add child nodes */
        if (!empty($tree->children_nodes)) {
            $c++;
            foreach ($tree->children_nodes as $id => $data) {
                $node_name = $data['name'];

                $this->nodeList[$data['id']] = [
                    'id' => $data['id'],
                    'name' => $this->space($c).$node_name,
                    'parent_id' => $data['parent_id'],
                    'slug' => $data['slug'],
                    'complimentory' => $data['complimentory'],
                    'duration' => $data['duration'],
                    'price' => $data['price'],
                    'color' => $data['color'],
                    'end_node' => $data['end_node'],
                    'active' => $data['active'],
                ];
            }
            $c--;
        }
        /* Process child groups recursively */
        foreach ($tree->children_groups as $id => $data) {
            $c++;
            $this->toList($data, $c);
            $c--;
        }
    }

    public function space($count)
    {
        $str = '';
        for ($i = 1; $i <= $count; $i++) {
            $str .= '&nbsp;&nbsp;&nbsp;';
        }

        return $str;
    }
}
