<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class ProdCategoriaModel {
		private $db;
		private $table = 'prod_categoria';
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
				->where('status', 1)
				->fetchAll();

			return $this->response->SetResponse(true);
		}

		public function get($id) {
			$this->response->result = $this->db
				->from($this->table)
				->where('id', $id)
				->fetch();

			if($this->response->result) { $this->response->SetResponse(true); }
			else { $this->response->SetResponse(false, 'no existe el registro'); }
			return $this->response;
		}

		public function getByNombreMD5($nombre) {
			$this->response->result = $this->db
				->from($this->table)
				->where('MD5(LOWER(nombre))', $nombre)
				->where('status', 1)
				->fetchAll();
			
			$this->response->total = $this->db
				->from($this->table)
				->select(NULL)->select('COUNT(*) AS total')
				->where('MD5(LOWER(nombre))', $nombre)
				->where('status', 1)
				->fetch()
				->total;

			return $this->response->SetResponse(true);;
		}

		public function getAll($pagina=0, $limite=0, $busqueda=0) {
			$busqueda = $busqueda!='0'? $busqueda: '_';
			if($limite == 0) {
				$this->response->result = $this->db
					->from($this->table)
					->where("nombre LIKE '%$busqueda%'")
					->where('status', 1)
					->orderBy('nombre ASC')
					->fetchAll();
			} else {
				$inicial = $pagina * $limite;
				$this->response->result = $this->db
					->from($this->table)
					->where("nombre LIKE '%$busqueda%'")
					->where('status', 1)
					->orderBy('nombre ASC')
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

		public function getImage($id) {
			$filename = "$id.jpg";
			$filePath = "assets/image/categorias/$filename";
			
			if(file_exists($filePath)) {
				$this->response->result = URL_API."assets/image/categorias/$filename";
				$this->response->SetResponse(true);
			} else {
				$this->response->result = URL_IMG_DEFAULT;
				$this->response->SetResponse(false, "No existe la imagen de la categoría $id");
			}

			return $this->response->SetResponse(true);
		}

		public function getByNombre($nombre) {
			$this->response->result = $this->db
				->from($this->table)
				->where("nombre = TRIM('$nombre')")
				->where('status', 1)
				->fetch();

			if($this->response->result)	$this->response->SetResponse(true);
			else	$this->response->SetResponse(false, 'No existe categoria con ese nombre');

			return $this->response;
		}

		/***
		 * Función para regresar los IDs de un grupo de categorias mediante su nombre separado por & y reemplazando ' ' por '_'
		 * recibe {cadena_categorias} lista de categorias concatenadas por '&' y reemplazando ' ' por '_'. Si recibe 0, devolverá 0
		 */
		public function getByNombres($cadena_categorias) {
			if((is_numeric($cadena_categorias && $cadena_categorias==0)) || strlen($cadena_categorias)==0) {
				$this->response->result = 0;
				$this->response->SetResponse(false, '');
			} else {
				$categoriesFound = explode('&', $cadena_categorias);
				$categories = [];
				foreach($categoriesFound as $category) {
					$cat = $this->getByNombre(str_replace('_', ' ', $category));
					if($cat->response) {
						$categories[] = $cat->result->id;
					}
				}

				$this->response->result = $categories;
				$this->response->SetResponse(true);
			}

			return $this->response;
		}
		
		public function add($data) {
			try{
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, 'id del registro: '.$this->response->result); }
				else { $this->response->SetResponse(false, 'no se inserto el registro'); }
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: add model $this->table");
			}

			return $this->response;
		}

		/*** edit ***/
		public function edit($data, $id) {
			try{
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, "id actualizado $id"); }
				else { $this->response->SetResponse(false, 'no se edito el registro'); }
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model $this->table");
			}

			return $this->response;
		}
		
		public function del($id) {
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
				$this->response->SetResponse(false, "catch: del model $this->table");
			}

			return $this->response;
		}

		public function saveImg($file, $id) {
			$directory  = 'assets/image/categorias/';
			$extension  = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);

			$filename = $id."_".time().".".$extension;
			// $filename = sprintf('%s.%0.8s', $basename, $extension);

			$file->moveTo($directory.DIRECTORY_SEPARATOR.$filename);

			$this->response->filename = $filename;
			return $this->response->SetResponse(true);
		}
	}
?>