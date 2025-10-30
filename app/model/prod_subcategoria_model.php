<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class ProdSubcategoriaModel {
		private $db;
		private $table = 'prod_subcategoria'; 
		private $response;

		public function __CONSTRUCT($db) {
			require_once './core/defines.php';
			$this->db = $db;
			$this->response = new Response();
		}

		public function find($busqueda) {
			$this->response->result = $this->db
				->from($this->table)
				->where("CONCAT_WS(' ', nombre, clave) LIKE '%$busqueda%'")
				->where('status = 1')
				->fetchAll();

			return $this->response->SetResponse(true);
		}
		
		public function get($id) {
			$this->response->result = $this->db
				->from($this->table)
				->where('id', $id)
				->where('status', 1)
				->fetch();

			if($this->response->result) { $this->response->SetResponse(true); }
			else { $this->response->SetResponse(false, 'No existe el registro'); }

			return $this->response;
		}

		public function getByNombreMD5($nombre, $prod_categoria_id=0) {
			$this->response->result = $this->db
				->from($this->table)
				->where('MD5(LOWER(nombre))', $nombre)
				->where(intval($prod_categoria_id)!=0? "prod_categoria_id = $prod_categoria_id": "TRUE")
				->where('status', 1)
				->fetchAll();

			$this->response->sql = "SELECT * FROM $this->table WHERE MD5(LOWER(nombre)) = $nombre AND ".(intval($prod_categoria_id)!=0? "prod_categoria_id = $prod_categoria_id": "TRUE")." AND status = 1";
			
			$this->response->total = $this->db
				->from($this->table)
				->select(NULL)->select('COUNT(*) AS total')
				->where('MD5(LOWER(nombre))', $nombre)
				->where(intval($prod_categoria_id)!=0? "prod_categoria_id = $prod_categoria_id": "TRUE")
				->where('status', 1)
				->fetch()
				->total;

			return $this->response->SetResponse(true);;
		}
		
		public function getAll($pagina=0, $limite=0, $busqueda=0, $prod_categoria_id=0) {
			$busqueda = $busqueda!='0'? $busqueda: '_';
			if($limite == 0) {
				$this->response->result = $this->db
					->from($this->table)
					->where("nombre LIKE '%$busqueda%'")
					->where("prod_categoria_id ".($prod_categoria_id==0? ">": "=").$prod_categoria_id)
					->where('status', 1)
					->orderBy('prod_categoria_id ASC, nombre ASC')
					->fetchAll();
			} else {
				$inicial = $pagina * $limite;
				$this->response->result = $this->db
					->from($this->table)
					->where("nombre LIKE '%$busqueda%'")
					->where("prod_categoria_id ".($prod_categoria_id==0? ">": "=").$prod_categoria_id)
					->where('status', 1)
					->orderBy('prod_categoria_id ASC, nombre ASC')
					->limit("$inicial, $limite")
					->fetchAll();
			}

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->where("nombre LIKE '%$busqueda%'")
				->where('status', 1)
				->fetch()
				->Total;

			return $this->response->SetResponse(true);
		}

		public function getByCategoria($prod_categoria_id) {
			$prod_categoria_id = "prod_categoria_id ".(is_integer($prod_categoria_id)? ($prod_categoria_id!=0? "=": ">")." $prod_categoria_id": "IN ($prod_categoria_id)");
			$this->response->test = $prod_categoria_id;
			$this->response->result = $this->db
				->from($this->table)
				->where($prod_categoria_id)
				->where('status', 1)
				->orderBy('prod_categoria_id ASC, nombre ASC')
				->fetchAll();
				
			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('count(*) as Total')
				->where($prod_categoria_id)
				->where('status', 1)
				->fetch()
				->Total;

			return $this->response->SetResponse(true);
		}
		
		public function getByNombre($nombre) {
			$this->response->result = $this->db
				->from($this->table)
				->where("nombre = TRIM('$nombre')")
				->where('status', 1)
				->fetch();

			if($this->response->result) { $this->response->SetResponse(true); }
			else { $this->response->SetResponse(false, 'No existe subcategoria con ese nombre'); }

			return $this->response;
		}

		/***
		 * Función para regresar los IDs de un grupo de subcategorias mediante su nombre separado por & y reemplazando ' ' por '_'
		 * recibe {cadena_subcategorias} lista de subcategorias concatenadas por '&' y reemplazando ' ' por '_'. Si recibe 0, devolverá 0
		 */
		public function getByNombres($cadena_subcategorias) {
			if((is_numeric($cadena_subcategorias && $cadena_subcategorias==0)) || strlen($cadena_subcategorias)==0) {
				$this->response->result = 0;
				$this->response->SetResponse(false);
			} else {
				$subcategoriesFound = explode('&', $cadena_subcategorias);
				$subcategories = [];
				foreach($subcategoriesFound as $subcategory) {
					$subcat = $this->getByNombre(str_replace('_', ' ', $subcategory));
					if($subcat->response) {
						$subcategories[] = $subcat->result->id;
					}
				}
				
				$this->response->result = $subcategories;
				$this->response->SetResponse(true);
			}

			return $this->response;
		}
		
		public function add($data){
			try {
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result) { return $this->response->SetResponse(true, 'id del registro: '.$this->response->result); } 
				else { return $this->response->SetResponse(false, 'no se inserto el registro'); }
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				return $this->response->SetResponse(false, "catch: add model: $this->table");
			}
		}
		
		public function edit($data, $id){
			try {
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();
					
				if($this->response->result) { $this->response->SetResponse(true, "id actualizado: $id"); } 
				else { $this->response->SetResponse(false, 'no se edito el registro'); }
			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model: $this->table");
			}

			return $this->response;
		}
		
		public function del($id){
			try{
				$data['status'] = 0;
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();
					
				if($this->response->result!=0) { $this->response->SetResponse(true, "id baja: $id"); } 
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model: $this->table");
			}

			return $this->response;
		}
	}
?>