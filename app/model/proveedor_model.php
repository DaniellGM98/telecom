<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class ProveedorModel {
		private $db;
		private $table = 'proveedor'; 
		private $response;
		
		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
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
					->select(NULL)->select('nombre, telefono, correo')
					->where("CONCAT_WS(' ', nombre, telefono, correo) LIKE ?" , "%$filtro%")
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


		/***
			Método getAllBusca
			Recibe: {pagina} pagina, {limite} limite o cuantos, {filtro} busqueda
			Si la busqueda es 0, no busca nada y regresa todos los proveedores
		 ***/ 
		public function getAllBusca($pagina, $limite, $filtro) {
			$filtro = $filtro!='0'? $filtro: '_';
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select('id, nombre, telefono, correo')
				->where('nombre LIKE ?' , "%$filtro%")
				->where('status', 1)
				->limit($limite)
				->offset($pagina)
				->orderBy('nombre ASC')
				->fetchAll();

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->where('nombre LIKE ?' , "%$filtro%")
				->where('status', 1)
				->fetch()
				->Total;

			return $this->response->SetResponse(true);
		}
		/*******************************/

		/*** add ***/
		public function add($data) {
			try{
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, 'id del registro: '.$this->response->result); }    
				else { $this->response->SetResponse(false, 'no se inserto el registro'); }

			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: add model proveedor $ex->getMessage");
			}

			return $this->response;
		}//fin de add

		/*** edit ***/
		public function edit($data, $id) {
			try{
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0)	$this->response->SetResponse(true);
				else { $this->response->SetResponse(false, 'no se edito el registro'); }

			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model proveedor $ex->getMessage");
			}

			return $this->response;
		}//fin de edit

		/*** del ***/
		public function del($id) {
			try{
				$data['status'] = 0;
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0)	$this->response->SetResponse(true);
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }

			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model proveedor $ex->getMessage");
			}

			return $this->response;
		}//fin de del
	}//fin de proveedor
?>