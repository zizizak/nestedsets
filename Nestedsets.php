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
	*/
	private $CI;
	private $db;

	/**
	* The main columns for nested sets
	*
	* $db_table: the name of the table
	* $primary_key: the primary key of the table
	* $primary_filter: filter the primary key
	* 					- intval for int keys 
	* 					- htmlentities for string keys
	* $parent: the parent node
	* $left_col: the name of the left column
	* $right_col: the name of the right column
	*/
	public $db_table;
	public $primary_key;
	public $primary_filter;
	public $parent;
	public $left_col;
	public $right_col;
	
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
		$this->db_table = (isset($config['db_table'])) ? $config['db_table'] : 'nestedsets';
		$this->primary_key = (isset($config['primary_key'])) ? $config['primary_key'] : 'id';
		$this->primary_filter = (isset($config['primary_filter'])) ? $config['primary_filter'] : 'intval';
		$this->parent = (isset($config['parent'])) ? $config['parent'] : 'parent_id';
		$this->left_col = (isset($config['left'])) ? $config['left'] : 'lft';
		$this->right_col = (isset($config['right'])) ? $config['right'] : 'rgt';
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

		// does the parent has any children?
		if ($this->has_children($parent_node)) {
			// appended the new node
			$data[$this->left_col] = intval($parent_node[$this->right_col]);
			$data[$this->right_col] = $parent_node[$this->right_col] + 1;
		} else {
			// add new node as 1st child
			$data[$this->left_col] = $parent_node[$this->left_col] + 1;
			$data[$this->right_col] = $parent_node[$this->left_col] + 2;
		}

		// increment (+2) the left and right values of all nodes
		// to the right of the new node
		$left = $this->left_col;
		$right = $this->right_col;

		$this->db->set($this->right_col, $right . '+' . 2 , false)
		->where($this->right_col . ' >', $parent_node[$this->left_col])
		->update($this->db_table);

		$this->db->set($this->left_col, $left . '+' . 2 , false)
		->where($this->left_col . ' >', $parent_node[$this->left_col])
		->update($this->db_table);

		// add the new node
		$this->db->insert($this->db_table, $data);
		return $this->db->insert_id();
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