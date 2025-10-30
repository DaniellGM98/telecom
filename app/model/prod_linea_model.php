<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class ProdLineaModel {
		private $db;
		private $table = 'prod_linea'; 
		private $response;

		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}

		/***
		 * Función find: busca a una linea de producto (categoria) por nombre o clave
		 * recibe {filter} puede ser nombre o clave
		 * regresa: arreglo de objetos con todas las coincidencias
		 * ***/
		public function find($filter) {
			$this->response->result = $this->db
				->from($this->table)
				->where("CONCAT_WS(' ', nombre, clave) LIKE '%$filter%'")
				->where('status', 1)
				->fetchAll();

			return $this->response->SetResponse(true);
		}
		/*** Fin de la función */

		/***
		 * Función para obtener los datos de una categoria o linea por su ID
		 * recibe {id} ID del registro en la base de datos
		 * regresa: objeto con la información de la linea con el ID recibido
		 */
		public function get($id) {
			$this->response->result = $this->db
				->from($this->table)
				->where('id_linea', $id)
				->where('status', 1)
				->fetch();

			if($this->response->result)	$this->response->SetResponse(true);
			else	$this->response->SetResponse(false, 'No existe linea con ese ID');

			return $this->response;
		}
		/*** Fin de la función */

		/***
		 * Función para obtener la información de la categoría mediante su nombre
		 * recibe {nombre} nombre de la categoría
		 * regresa: objeto con la información de la categoría
		 */
		public function getByNombre($nombre) {
			$this->response->result = $this->db
				->from($this->table)
				->where("nombre = TRIM('$nombre')")
				->where('status', 1)
				->fetch();

			if($this->response->result)	$this->response->SetResponse(true);
			else	$this->response->SetResponse(false, 'No existe linea con ese nombre');

			return $this->response;
		}
		/*** Fin de la función */

		/*** 
		 * Función para obtener todas las categorias activas de la base de datos
		 * recibe opcional {limit} número de registros a regresar
		 * regresa: objeto con todos los registros de las categorias de la base de datos
		 * ***/
		public function getAll($limit=null) {
			if($limit == null) {
				$this->response->result = $this->db
					->from($this->table)
					->where('status', 1)
					->orderBy('id_linea')
					->fetchAll();
			} else {
				$this->response->result = $this->db
					->from($this->table)
					->where('status', 1)
					->orderBy('id_linea')
					->limit($limit)
					->fetchAll();
			}

			return $this->response->SetResponse(true);
		}
		/*** Fin de la función */

		/***
		 * Función para agregar una nueva categoria en la base de datos
		 * recibe {data} Arreglo con la información del nuevo registro
		 * regresa: ID del nuevo registro
		 * ***/
		public function add($data){
			try {
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result) {
					return $this->response->SetResponse(true, 'id del registro: '.$this->response->result);
				} else {
					return $this->response->SetResponse(false, 'no se inserto el registro');
				}
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				return $this->response->SetResponse(false, "catch: add model prod_linea: ".$ex->getMessage());
			}
		}
		/*** Fin de la función add */

		/***
		 * Función para editar un registro de prod_linea mediante su ID del registro
		 * recibe {data} Información de la categoría actualizada
		 * recibe {ID} ID del registro a actualizar
		 * ***/
		public function edit($data, $id){
			try {
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id_linea', $id)
					->execute();
					
				if($this->response->result) {
					$this->response->SetResponse(true, "id actualizado $id");    
				} else {
					$this->response->SetResponse(false, 'no se edito el registro');
				}
			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model prod_linea: ".$ex->getMessage());
			}

			return $this->response;
		}
		/*** Fin de la función edit */

		/****
		 * Función para dar de baja una categoria mediante su ID
		 * recibe {id} ID del registro a dar de baja
		 */
		public function del($id){
			try{
				$data['status'] = 0;
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id_linea', $id)
					->execute();
					
				if($this->response->result!=0) {
					$this->response->SetResponse(true, 'id baja: '.$id);    
				} else {
					$this->response->SetResponse(false, 'no se dio de baja el registro');
				}
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model prod_linea: ".$ex->getMessage());
			}

			return $this->response;
		}
		/*** Fin de la función del */

		/***
		 * Función para obtener la ruta relativa donde se encuentra la imagen de la categoría con el ID específico
		 * recibe {id_linea} ID de la categoría
		 * regresa: ruta relativa de la imagen de la categoría
		 * ***/
		public function getImage($id_linea) {
			require_once './core/defines.php';

			$filename = "$id_linea.jpg";
			$filePath = "assets/image/categorias/$filename";
			
			if(file_exists($filePath)) {
				$this->response->result = URL_API."assets/image/categorias/$filename";
				$this->response->SetResponse(true);
			} else {
				$this->response->result = URL_IMG_DEFAULT;
				$this->response->SetResponse(false, "No existe la imagen de la categoría $id_linea");
			}

			return $this->response->SetResponse(true);
		}
		/*** Fin de la función */

		/***
		 * Función para obtener la ruta relativa donde se encuentra la imagen de la categoría con el ID específico
		 * recibe {id_linea} ID de la categoría
		 * regresa: ruta relativa de la imagen de la categoría
		 * ***/
		public function getIcono($id_linea) {
			require_once './core/defines.php';

			$filename = $id_linea."_ico.png";
			$filePath = "assets/image/categorias/$filename";
			
			if(file_exists($filePath)) {
				$this->response->result = URL_API."assets/image/categorias/$filename";
				$this->response->SetResponse(true);
			} else {
				$this->response->result = URL_IMG_DEFAULT;
				$this->response->SetResponse(false, "No existe la imagen de la categoría $id_linea");
			}

			return $this->response->SetResponse(true);
		}
		/*** Fin de la función */

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
						$categories[] = $cat->result->id_linea;
					}
				}
				$this->response->result = $categories;
				$this->response->SetResponse(true);
			}

			return $this->response;
		}
		/*** Fin de la función */
	}
	/*** Fin  de la clase ProdLineaModel */
?>