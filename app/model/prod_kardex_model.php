<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class ProdKardexModel {
		private $db;
		private $table = 'prod_kardex'; 
		private $tableS = 'sucursal'; 
		private $response;
		
		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}
	
		/*** get  por ID ***/
		public function get($id_prod_kardex) {
			$this->response->result = $this->db
				->from($this->table)
				->where('id_prod_kardex', $id_prod_kardex)
				->fetch();

			if($this->response->result)	return $this->response->SetResponse(true,' ');
			else	return $this->response->SetResponse(false,'no existe el registro');

			return $this->response;
		}// fin de get

		/*** find ***/
		public function find($filtro) {
			$this->response->result = $this->db
				->from($this->table)
				->select(NULL)->select('sucursal_id, producto_id, fk_usuario, fecha, tipo, inicial, cantidad, final')
				->where("CONCAT_WS(' ', sucursal_id, producto_id, fk_usuario, fecha, tipo, inicial, cantidad, final) LIKE ?" , "%$filtro%")
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
				->from($this->table)->select(null)
				->select('COUNT(*) Total')
				->where('status', 1)
				->fetch()
				->Total;

			return $this->response->SetResponse(true);
		}// fin de getAll 

		/*** Ruta para obtener el stock por medio de la sucursal
		 * Recibe $sucursal_id id_prod_kardex de sucursal, $producto_id id_prod_kardex del producto 
		 * Actualización: 17-10-19
		 * Actualizo: Angel Gabriel Ramirez Alva
		 **/
		public function getStockSuc($sucursal_id, $producto_id) {
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select('final')
				->where('sucursal_id', $sucursal_id)
				->where('producto_id', $producto_id)
				->where('status', 1)
				->orderBy('fecha desc, id desc')
				->fetch();

			return $this->response->SetResponse(true, $this->response->result);
		}// fin de getAll 

		public function getByProducto($producto_id, $inicio, $fin) {
			$this->response->result = $this->db
				->from($this->table)
				->select("$this->tableS.nombre AS sucursal")
				->leftJoin("$this->tableS ON $this->tableS.id = sucursal_id")
				->where('producto_id', $producto_id)
				->where("CAST(fecha AS DATE) BETWEEN '$inicio' AND '$fin'")
				->where("$this->table.status", 1)
				->where("$this->tableS.status", 1)
				->orderBy('fecha asc')
				->fetchAll();

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->where('producto_id', $producto_id)
				->where("CAST(fecha AS DATE) BETWEEN '$inicio' AND '$fin'")
				->where("$this->table.status", 1)
				->where("$this->tableS.status", 1)
				->fetch()
				->Total;

			return $this->response->SetResponse(true);
		}// fin de getAll 

		/***
		 * Ruta para obtener el kardex de producto por medio de la sucursal
		 * Recibe $sucursal_id id_prod_kardex de sucursal, $producto_id id_prod_kardex del producto 
		 * Creación: 18-10-2019
		 * Autor: Angel Gabriel Ramirez Alva
		 **/
		public function getKardexSucursal($producto_id, $sucursal_id, $inicio, $fin) {
			$this->response->result = $this->db
				->from($this->table)
				->where('sucursal_id', $sucursal_id)
				->where('producto_id', $producto_id)
				->where('CAST(fecha AS DATE) >= ?', $inicio)
				->where('CAST(fecha AS DATE) <= ?', $fin)
				->where('status', 1)
				->orderBy('fecha asc')
				->fetchAll();

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->where('sucursal_id', $sucursal_id)
				->where('producto_id', $producto_id)
				->where('CAST(fecha AS DATE) >= ?', $inicio)
				->where('CAST(fecha AS DATE) <= ?', $fin)
				->where('status', 1)
				->fetch()
				->Total;

			return $this->response->SetResponse(true);
		}// fin de getAll 

		/*** add ***/
		public function add($data) {
			//date_default_timezone_set('America/Mexico_City');
			//$data['fecha'] = date("Y-m-d H:i:s");
			try{
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result!=0)	$this->response->SetResponse(true, 'id_prod_kardex del registro: '.$this->response->result);    
				else { $this->response->SetResponse(false, 'no se inserto el registro'); }

			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: add model prod_kardex');
			}

			return $this->response;
		}//fin de add

		/*** edit ***/
		public function edit($data, $id_prod_kardex) {
			try{
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id_prod_kardex)
					->execute();

				if($this->response->result!=0)	$this->response->SetResponse(true, 'id_prod_kardex actualizado: '.$id_prod_kardex);    
				else { $this->response->SetResponse(false, 'no se edito el registro'); }

			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: edit model prod_kardex');
			}

			return $this->response;
		}//fin de edit

		/*** del ***/
		public function del($id_prod_kardex) {
			try{
				$data['status'] = 0;
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id_prod_kardex', $id_prod_kardex)
					->execute();

				if($this->response->result!=0)	$this->response->SetResponse(true, 'id_prod_kardex baja: '.$id_prod_kardex);    
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }

			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: del model prod_kardex');
			}

			return $this->response;
		}//fin de del

		public function getKardexSucFrom($producto_id, $sucursal_id, $desde = '2021-05-01') {
			$result = $this->db
				->from($this->table)
				->where('sucursal_id', $sucursal_id)
				->where('producto_id', $producto_id)
				->where('CAST(fecha AS DATE) >= ?', $desde)
				->where('status', 1)
				->orderBy('id asc')
				->fetchAll();

			return $result;
		}
	}//fin de prod_kardex
?>