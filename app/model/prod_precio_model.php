<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class ProdPrecioModel {
		private $db;
		private $table = 'prod_precio';
		private $tableL = 'prod_lista_precio';
		private $response;
		
		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}
	
		/*** get  por ID ***/
		public function get($id) {
			$this->response->result = $this->db
				->from($this->table)
				->where('id', $id)
				->fetch();

			if($this->response->result)	$this->response->SetResponse(true);
			else { $this->response->SetResponse(false, 'no existe el registro'); }

			return $this->response;
		}// fin de get

		/*** find ***/
		public function find($filtro) {
			$this->response->result = $this->db
				->from($this->table)
				->select(NULL)->select('producto_id, lista_precio_id, precio, actualizado')
				->where("CONCAT_WS(' ', producto_id, lista_precio_id, precio, actualizado) LIKE ?" , "%$filtro%")
				->fetchAll();

			return $this->response->SetResponse(true);
		}//fin find

		/*** getAll ***/
		public function getAll() {
			$this->response->result = $this->db
				->from($this->table)
				->where('status', 1)
				->fetchAll();

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->where('status', 1)
				->fetch()
				->Total;

			return $this->response->SetResponse(true);
		}// fin de getAll 

		/*** getPrecio ***/
		public function getPrecio($producto_id, $sucursal_id) {
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select("precio, $this->tableL.nombre, $this->tableL.id")
				->leftJoin("$this->tableL ON $this->tableL.id = $this->table.lista_precio_id")
				->where("$this->tableL.sucursal_id", $sucursal_id)
				->where("producto_id", $producto_id)
				->where("origen", 0)
				->where("$this->tableL.status", 1)
				->fetchAll();

			return $this->response->SetResponse(true);
		}// fin de getPrecio

		/*** get  por producto y lista precio ***/
		public function getProdPrecio($producto_id, $lista_precio_id) {
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select("$this->table.id, precio, descuento")
				->where('producto_id', $producto_id)
				->where('lista_precio_id', $lista_precio_id)
				->innerJoin("$this->tableL on $this->table.lista_precio_id = $this->tableL.id")
				->fetch();

			if($this->response->result) { $this->response->SetResponse(true); }
			else { $this->response->SetResponse(false, 'no existe el registro'); }
			return $this->response;
		}// fin de getProdPrecio

		/*** get  por producto y lista precio ***/
		public function getListaPrecio($producto_id) {
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select('lista_precio_id, precio')
				->where('producto_id', $producto_id)
				->fetchAll();

			if($this->response->result) { $this->response->SetResponse(true); }
			else { $this->response->SetResponse(false, 'no existe el registro'); }

			return $this->response;
		}// fin de getProdPrecio

		/* Regresa todos los productos de la lista 1 es decir la general original
		 * Recibe el $id del registro original
		 */
		public function getAllOrignial($id) {
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select('producto_id, precio')
				->where('lista_precio_id', $id)
				->fetchAll();

			if($this->response->result) { $this->response->SetResponse(true); }
			else { $this->response->SetResponse(false, 'no existe el registro'); }

			return $this->response;
		}// fin 

		/*** add ***/
		public function add($data) {
			date_default_timezone_set('America/Mexico_City');
			$data['actualizado'] = date("Y-m-d H:i:s");
			try {
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result!=0) $this->response->SetResponse(true, 'id del registro: '.$this->response->result);    
				else { $this->response->SetResponse(false, 'no se inserto el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: add model prod_precio');
			}
		
			return $this->response;
		}//fin de add

		/*** edit ***/
		public function edit($data, $id) {
			try {
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, "id actualizado: $id"); }    
				else { $this->response->SetResponse(false, 'no se edito el registro'); }
		
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: edit model prod_precio');
			}

			return $this->response;
		}//fin de edit

		/* editProdLista(producto_id, lista, precio)
		 * Recibe
		 * $producto_id: id del producto
		 * $lista_precio_id: id de la lista de precios
		 * $precio: precio de la lista para actualizar
		 * Regresa 1, si fue actualizado 0 si no se actualizo
		 * Creación: 17 de Octubre 2019
		 * Autor: Angel Gabriel Ramirez Alva
		 */
		public function editProdLista($producto_id, $lista_precio_id, $precio) {
			date_default_timezone_set('America/Mexico_City');
			$data['actualizado'] = date("Y-m-d H:i:s");
			$data['precio'] = $precio;
			
			try {
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('producto_id', $producto_id)
					->where('lista_precio_id', $lista_precio_id)
					->where('status',1)
					->execute();

				if($this->response->result!=0)	$this->response->SetResponse(true, 'precio actualizado: '.$precio);
				else { $this->response->SetResponse(false, 'no se edito el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: editProdLista model prod_precio');
			}

			return $this->response;
		}//fin de edit

		/*** del ***/
		public function del($id) {
			try {
				$data['status'] = 0;
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, "id baja: $id");  }
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: del model prod_precio');
			}

			return $this->response;		
		}//fin de del
	}//fin de prod_precio
?>