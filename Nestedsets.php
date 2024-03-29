<?php 
/**
* NestedSets
* An implementation of Joe Celko's Nested Sets as a CodeIgniter model.
*
* @package application/libraries
* @author Lykourgos Tsirikos
*/
class NestedSets
{
	/**
	* Hold the CI and db instance
	*
	* @access private
	* @var 	CI instance
	* @var 	db instance
	*/
	private $CI;
	private $db;

	/**
	* The main columns for nested sets
	*
	* @access public
	* @var 	$db_table: the name of the table
	* @var 	$primary_key: the primary key of the table
	* @var 	$primary_filter: filter the primary key
	* 					- intval for int keys 
	* 					- htmlentities for string keys
	* @var 	$parent: the parent node
	* @var 	$left_col: the name of the left column
	* @var 	$right_col: the name of the right column
	*/
	public $db_table = 'nestedsets';
	public $primary_key = 'id';
	public $primary_filter = 'intval';
	public $parent = 'parent_id';
	public $left_col = 'lft';
	public $right_col = 'rgt';
	
	function __construct($config = array()){
		$CI =& get_instance();
		$this->db =& $CI->db;

		// initialize the library
		$this->initialize($config);
	}

	/**
	* Initialize library
	*
	* @param array 		$config 		
	*/
	public function initialize($config){
		// set some defaults 
		$this->db_table = (isset($config['db_table'])) ? $config['db_table'] : $this->db_table;
		$this->primary_key = (isset($config['primary_key'])) ? $config['primary_key'] : $this->primary_key;
		$this->primary_filter = (isset($config['primary_filter'])) ? $config['primary_filter'] : $this->primary_filter;
		$this->parent = (isset($config['parent'])) ? $config['parent'] : $this->parent;
		$this->left_col = (isset($config['left'])) ? $config['left'] : $this->left_col;
		$this->right_col = (isset($config['right'])) ? $config['right'] : $this->right_col;
	}

	/* --------------------------------------------------------------
	* 	NODE MANIPULATION FUNCTIONS (Inserting & Deleting)
	* ------------------------------------------------------------ */

	/**
	* Add a new node
	*
	* @param 	array 	$data
	* @return 	int 	id
	*/
	public function insert_node($data){
		// lets make some space first ...

		// find the node with the biggest 'right' value (i.e last inseted) 
		$this->db->select_max($this->right_col, 'lft');
		$query = $this->db->get($this->db_table);
		$node = $query->row_array();

		// a small hack so that the query won't break
		$left = (is_null($node['lft'])) ? 0 : $node['lft'] ;

		// update left & right values of all nodes on the right of the new one
		$this->db->set($this->left_col, $left + 2)
		->where($this->left_col . ' >', $left)
		->update($this->db_table);

		$this->db->set($this->right_col, $left + 2)
		->where($this->right_col . ' >', $left)
		->update($this->db_table);

		// insert new node
		$data[$this->left_col] = $left + 1; 
		$data[$this->right_col] = $left + 2;

		$this->db->insert($this->db_table, $data);
		return $this->db->insert_id();
	}

	/**
	* Add a new child to parent node that has NO children
	*
	* @param 	array 	$node
	* @return 	int 	id
	*/
	public function insert_child($data){
		// find the parent node 
		$filter = $this->primary_filter;
		$this->db->select($this->left_col .', ' .$this->right_col)
		->where($this->primary_key, $filter($data['parent_id']));
		$parent_node = $this->db->get($this->db_table)->row_array();

		// increment (+2) the left and right values of all nodes
		// to the right of the new node
		$left = $this->left_col;
		$right = $this->right_col;

		$this->db->set($this->left_col, $left . '+' . 2 , false)
		->where($this->left_col . ' >', $parent_node[$this->left_col])
		->update($this->db_table);

		$this->db->set($this->right_col, $right . '+' . 2 , false)
		->where($this->right_col . ' >', $parent_node[$this->left_col])
		->update($this->db_table);

		// add the new node
		$data[$this->left_col] = $parent_node[$this->left_col] + 1;
		$data[$this->right_col] = $parent_node[$this->left_col] + 2;
	
		$this->db->insert($this->db_table, $data);
		return $this->db->insert_id();
	}

	/**
	* Remove all nodes from the tree, 
	* i.e delete all records from table 
	*/
	public function delete_tree(){
		return $this->db->empty_table($this->db_table);
	}

	/**
	* Delete a node from the table/tree without
	* deleting children
	*/
	public function delete_node($node){

		// find the left and right values of the node to delete
		$filter = $this->primary_filter;
		$this->db->select($this->parent .', ' . $this->left_col .', ' . $this->right_col)
		->select($this->right_col . '-' . $this->left_col . '+'. 1 .' AS width', false)
		->where($this->primary_key, $filter($node));
		$node = $this->db->get($this->db_table)->row_array();

		// r.i.p node ...
		$this->db->where($this->left_col, (int)$node[$this->left_col])->delete($this->db_table);

		// bring orphans (child nodes) to the same level as their parent node was
		$left = $this->left_col;
		$right = $this->right_col;

		$this->db->set($this->right_col, $right . '-' . 1, false)
		->set($this->left_col, $left . '-' . 1, false)
		->set($this->parent, (int)$node[$this->parent])
		->where($this->left_col . ' BETWEEN ' . (int)$node[$this->left_col] . ' AND ' . (int)$node[$this->right_col], null, false)
		->update($this->db_table);

		$this->db->set($this->right_col, $right . '-' . 2, false)
		->where($this->right_col . ' >', (int)$node[$this->right_col])
		->update($this->db_table);

		$this->db->set($this->left_col, $left . '-' . 2, false)
		->where($this->left_col . ' >', (int)$node[$this->right_col])
		->update($this->db_table);
	}

	/**
	* Delete a node from the table/tree and any children
	*/
	public function delete_with_children($node){

		// find the left and right values of the node to delete
		$filter = $this->primary_filter;
		$this->db->select($this->left_col .', ' . $this->right_col)
		->select($this->right_col . ' - ' . $this->left_col . ' + 1 AS width', false)
		->where($this->primary_key, $filter($node));
		$node = $this->db->get($this->db_table)->row_array();

		// delete the node and its children
		$this->db->where($this->left_col . ' BETWEEN ' . (int)$node[$this->left_col] . ' AND ' . (int)$node[$this->right_col], null, false);
		$this->db->delete($this->db_table);

		$left = $this->left_col;
		$right = $this->right_col;

		$this->db->set($this->right_col, $right . ' + ' . (int)$node['width'], false)
		->where($this->right_col . ' >', (int)$node[$this->right_col])
		->update($this->db_table);

		$this->db->set($this->left_col, $left . ' + ' . (int)$node['width'], false)
		->where($this->left_col . ' >', (int)$node[$this->right_col])
		->update($this->db_table);
	}

	/**
	* Truncates the table. 
	* NOTE! use with extreme caution!
	*/
	public function truncate(){
		return $this->db->truncate($this->db_table);
	}

	/* --------------------------------------------------------------
	* 	UTILITY (TESTING) FUNCTIONS
	* ------------------------------------------------------------ */

	/**
	* Check if node has children
	*
	* @param 	array $node
	* @return 	bool
	*/
	public function has_children($node){
		$result = $node[$this->right_col] - $node[$this->left_col];
		return ($result > 1) ? true : false ;
	}

	/**
	* Return the number of descendants (children) of a node
	*
	* @param 	array 	$node
	* @return 	int
	*/
	public function number_of_children($node){
		return (($node[$this->right_col] - $node[$this->left_col] - 1) / 2);
	}
}

/* End of file NestedSets.php */
/* Location: ./application/libraries/NestedSets.php */