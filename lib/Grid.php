<?
class Grid extends CompleteLister {
    protected $columns;
    private $table;
    private $id;
    
	public $last_column;
    public $sortby='0';
    public $sortby_db=null;

    public $displayed_rows=0;

    public $totals_t=null;
    /**
     * Inline related property
     * If true - TAB key submits row and activates next row
     */
    public $tab_moves_down=false;
    /**
     * Inline related property
     * Wether or not to show submit line
     */
    public $show_submit=true;
    private $record_order=null;

    function init(){
        parent::init();
        $this->api->addHook('pre-render',array($this,'precacheTemplate'));

        $this->sortby=$this->learn('sortby',$_GET[$this->name.'_sort']);
        $this->api->addHook('post-submit', array($this,'submitted'));
    }
    function defaultTemplate(){
        return array('grid','grid');
    }
    function addColumn($type,$name=null,$descr=null){
        if($name===null){
            $name=$type;
            $type='text';
        }
        if($descr===null)$descr=$name;
        $this->columns[$name]=array(
                'type'=>$type,
                'descr'=>$descr
                );

        $this->last_column=$name;

        return $this;
    }
    function addButton($label){
        return $this->add('Button',count($this->elements),'grid_buttons')
            ->setLabel($label)->onClick();
    }
    function addQuickSearch($fields,$class='QuickSearch'){
        return $this->add($class,null,'quick_search')
            ->useFields($fields);
    }
    function makeSortable($db_sort=null){
        // Sorting
        $reverse=false;
        if(substr($db_sort,0,1)=='-'){
            $reverse=true;
            $db_sort=substr($db_sort,1);
        }
        if(!$db_sort)$db_sort=$this->last_column;

        if($this->sortby==$this->last_column){
            // we are already sorting by this column
            $info=array('1',$reverse?0:("-".$this->last_column));
            $this->sortby_db=$db_sort;
        }elseif($this->sortby=="-".$this->last_column){
            // We are sorted reverse by this column
            $info=array('2',$reverse?$this->last_column:'0');
            $this->sortby_db="-".$db_sort;
        }else{
            // we are not sorted by this column
            $info=array('0',$reverse?("-".$this->last_column):$this->last_column);
        }
        $this->columns[$this->last_column]['sortable']=$info;

        return $this;
    }
    function format_number($field){
    }
    function format_text($field){
    	$this->current_row[$field] = $this->current_row[$field];
    }
    function format_shorttext($field){
    	$text=$this->current_row[$field];
    	if(strlen($text)>50)$text=substr($text,0,46).' ...';
    	$this->current_row[$field]=$text;
    }
    function format_html($field){
    	$this->current_row[$field] = htmlentities($this->current_row[$field]);
    }
    function format_money($field){
        $m=$this->current_row[$field];
        $this->current_row[$field]=number_format($m,2);
        if($m<0){
            $this->current_row[$field]='<font color=red>'.$this->current_row[$field].'</font>';
        }
    }
    function format_totals_number($field){
        return $this->format_number($field);
    }
    function format_totals_money($field){
        return $this->format_money($field);
    }
	function format_time($field){
		$this->current_row[$field]=format_time($this->current_row[$field]);
	}
    function format_nowrap($field){
    	$this->row_t->set("tdparam_$field", $this->row_t->get("tdparam_$field")." nowrap");
    }
    function format_wrap($field){
    	$this->row_t->set("tdparam_$field", str_replace('nowrap','wrap',$this->row_t->get("tdparam_$field")));
    }
    function format_template($field){
        $this->current_row[$field]=$this->columns[$field]['template']
            ->set($this->current_row)
            ->trySet('_value_',$this->current_row[$field])
            ->render();
    }
    function format_expander($field, $idfield='id'){
        $n=$this->name.'_'.$field.'_'.$this->current_row[$idfield];
        $this->row_t->set('tdparam_'.$field,'id="'.$n.'" nowrap style="cursor: hand" onclick=\''.
                'expander_flip("'.$this->name.'",'.$this->current_row[$idfield].',"'.
                    $field.'","'.
                    $this->api->getDestinationURL($this->api->page.'_'.$field,array('expander'=>$field,
                            'cut_object'=>$this->api->page.'_'.$field, 'expanded'=>$this->name)).'&id=")\'');
        if($this->current_row[$field]){
            $this->current_row[$field]='<font color="blue">'.$this->current_row[$field].'</font>';
        }else{
            $this->current_row[$field]='<font color="blue">['.$this->columns[$field]['descr'].']</font>';
        }
    }
    function format_inline($field, $idfield='id'){
    	/**
    	 * Formats the InlineEdit: field that on click should substitute the text
    	 * in the columns of the row by the edit controls 
    	 * 
    	 * The point is to set an Id for each column of the row. To do this, we should
    	 * set a property showing that id should be added in prerender
    	 */
    	$col_id=$this->name.'_'.$field.'_inline';
    	$show_submit=$this->show_submit?'true':'false';
    	$tab_moves_down=$this->tab_moves_down?'true':'false';

    	$this->row_t->set('tdparam_'.$field, 'id="'.$col_id.'_'.$this->current_row[$idfield].
			'" style="cursor: hand"');
    	$this->current_row[$field]='<a href=\'javascript:'.
			'inline_show("'.$this->name.'","'.$col_id.'",'.$this->current_row[$idfield].', "'.
			$this->api->getDestinationURL($this->api->page, array(
			'cut_object'=>$this->api->page, 'submit'=>$this->name)).
			'", '.$tab_moves_down.', '.$show_submit.');\'>'.$this->current_row[$field].'</a>';
    }
    function format_nl2br($field) {
    	$this->current_row[$field] = nl2br($this->current_row[$field]);
    }
    function format_order($field, $idfield='id'){
        $n=$this->name.'_'.$field.'_'.$this->current_row[$idfield];
    	$this->row_t->set("tdparam_$field", 'id="'.$n.'" style="cursor: hand"'); 
    	$this->current_row[$field]=$this->record_order->getCell($this->current_row['id']);
    }
    function addRecordOrder($field){
    	if(!$this->record_order){
    		$this->record_order=$this->add('RecordOrder');
    		$this->record_order->setField($field);
    	}
    	return $this;
    }
    function setSource($table,$db_fields="*"){
        parent::setSource($table,$db_fields);
        if($this->sortby){
            $desc=false;
            $order=$this->sortby_db;
            if(substr($this->sortby_db,0,1)=='-'){
                $desc=true;
                $order=substr($order,1);
            }
            $this->dq->order($order,$desc);
        }
        //we always need to calc rows
        $this->dq->calc_found_rows();
        return $this;
    }
    function setTemplate($template){
        // This allows you to use Template 
        $this->columns[$this->last_column]['template']=$this->add('SMlite')
            ->loadTemplateFromString($template);
        return $this;
    }

	function submitted(){
		if($_GET['submit']!=$this->name)return;// false;
		//saving to DB
		if($_GET['action']=='update'){
			$this->update();
		}
		$row=$this->getRowAsCommaString($_GET['row_id']);
		echo $row;
		exit;
	}
	function update(){
		foreach($_GET as $name=>$value){
			if(strpos($name, 'field_')!==false){
				$this->dq->set(substr($name, 6)."='$value'");
			}
		}
		$idfield=$this->dq->args['fields'][0];
		if($idfield=='*')$idfield='id';
		$this->dq->where($idfield, $_GET['row_id']);
		$this->dq->do_update();
	}

	function getRowAsCommaString($id){
		$idfield=$this->dq->args['fields'][0];
		if($idfield=='*'||strpos($idfield,',')!==false)$idfield='id';
		$this->dq->where($idfield."=$id");
		//we should switch off the limit or we won't get any value
		$this->dq->limit(1);
		$row=$this->api->db->getHash($this->dq->select());
		$row_t=$this->template->cloneRegion('row');
		$row_t->del('cols');
        $col = $row_t->cloneRegion('col');

        foreach($this->columns as $name=>$column){
            $col->del('content');
            $col->set('content','<?$'.$name.'?>');

            // some types needs control over the td

            $col->set('tdparam','<?tdparam_'.$name.'?>nowrap<?/?>');
            $row_t->append('cols',$col->render());
        }
        $this->row_t = $this->api->add('SMlite');
        $this->row_t->loadTemplateFromString($row_t->render());

		$row=$this->formatRow($row);
		$result="";
		foreach($this->columns as $name=>$column){
			$result.=$row[$name]."<row_end>";
		}
		return $result;
	}
	function getRowHTML($id){
		$idfield=$this->dq->args['fields'][0];
		if($idfield=='*')$idfield='id';
		$this->dq->where($idfield."=$id");
		//we should switch off the limit or we won't get any value
		$this->dq->limit(1);
		$row=$this->api->db->getHash($this->dq->select());
		$row_t=$this->template->cloneRegion('row');
		$row_t->del('cols');
        $col = $row_t->cloneRegion('col');

        foreach($this->columns as $name=>$column){
            $col->del('content');
            $col->set('content','<?$'.$name.'?>');

            // some types needs control over the td

            $col->set('tdparam','<?tdparam_'.$name.'?>nowrap<?/?>');
            $row_t->append('cols',$col->render());
        }
        $this->row_t = $this->api->add('SMlite');
        $this->row_t->loadTemplateFromString($row_t->render());
		
		$row=$this->formatRow($row);
        $this->row_t->set($row);
        $result=$this->row_t->render();
        $tr_start=strpos($result, '<td');
        $result="\n ".substr($result, $tr_start, strpos($result, '</tr>')-$tr_start);
		return $result;
	}
    function formatRow($row=null){
    	if($row!=null)$this->current_row=$row;
        foreach($this->columns as $tmp=>$column){ // $this->cur_column=>$column){
            $formatters = split(',',$column['type']);
            foreach($formatters as $formatter){
                if(method_exists($this,$m="format_".$formatter)){
                    $this->$m($tmp);
                }else throw new BaseException("Grid does not know how to format type: ".$formatter);
            }
            if($this->current_row[$tmp]=='')$this->current_row[$tmp]='&nbsp;';
        }
        return $this->current_row;
    }
    function formatTotalsRow(){
        foreach($this->columns as $tmp=>$column){
            $formatters = split(',',$column['type']);
            $all_failed=true;
            foreach($formatters as $formatter){
                if(method_exists($this,$m="format_totals_".$formatter)){
                    $all_failed=false;
                    $this->$m($tmp);
                }
            }
            if($all_failed)$this->current_row[$tmp]='-';
        }
    }
    function precacheTemplate(){
        // pre-cache our template for row
        $row = $this->row_t;

        $col = $row->cloneRegion('col');
        $header = $this->template->cloneRegion('header');
        $header_col = $header->cloneRegion('col');
        $header_sort = $header_col->cloneRegion('sort');


        if($t_row = $this->totals_t){
            $t_col = $t_row->cloneRegion('col');
            $t_row->del('cols');
        }

        $row->set('grid_name',$this->name);
        $row->set('row_id','<?$id?>');
        $row->set('odd_even','<?$odd_even?>');
        $row->del('cols');
        $header->del('cols');
        if(count($this->columns )>0)
        foreach($this->columns as $name=>$column){
            $col->del('content');
            $col->set('content','<?$'.$name.'?>');

            if($t_row){
                $t_col->del('content');
                $t_col->set('content','<?$'.$name.'?>');
                $t_row->append('cols',$t_col->render());
            }

            // some types needs control over the td

            $col->set('tdparam','<?tdparam_'.$name.'?>nowrap<?/?>');

            $row->append('cols',$col->render());

            $header_col->set('descr',$column['descr']);
            if(isset($column['sortable'])){
                $s=$column['sortable'];
                // calculate sortlink
                $l = $this->api->getDestinationURL(null,array($this->name.'_sort'=>$s[1]));

                $header_sort->set('order',$column['sortable'][0]);
                $header_sort->set('sortlink',$l);
                $header_col->set('sort',$header_sort->render());
            }else{
                $header_col->del('sort');
            }
            $header->append('cols',$header_col->render());
        }
        $this->row_t = $this->api->add('SMlite');
        $this->row_t->loadTemplateFromString($row->render());

        if($t_row){
            $this->totals_t = $this->api->add('SMlite');
            $this->totals_t->loadTemplateFromString($t_row->render());
        }

        $this->template->set('header',$header->render());
        $this->template->set('grid_name',$this->name);
        //var_dump(htmlspecialchars($this->row_t->tmp_template));
        
    }
    function render(){
    	if($this->dq&&$this->dq->foundRows()==0){
    		$not_found=$this->add('SMlite')->loadTemplate('grid');
    		$not_found=$not_found->get('not_found');
    		//$this->template->del('header');
    		$this->template->del('rows');
    		$this->template->del('totals');
    		$this->template->set('header','<tr class="header">'.$not_found.'</tr>');
    		//return;
    	}
        parent::render();
    }
    
    public function setWidth( $width ){
    	$this->template->set('container_style', 'margin: 0 auto; width:'.$width.((!is_numeric($width))?'':'px'));
    	return $this;
    }
}
