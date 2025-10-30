<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class ProdListaPrecioModel {
		private $db;
		private $table = 'prod_lista_precio'; 
		private $tableS = 'sucursal'; 
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

			if($this->response->result) $this->response->SetResponse(true,' ');
			else { $this->response->SetResponse(false, 'no existe el registro'); }

			return $this->response;
		}// fin de get

		/*** find ***/
		public function find($filtro) {
			$this->response->result = $this->db
				->from($this->table)
				->select(NULL)->select('nombre, descuento')
				->where("CONCAT_WS(' ', nombre, descuento) LIKE ?" , "%$filtro%")
				->fetchAll();

			return $this->response->SetResponse(true);
		}//fin find

		/*** getAll ***/
		public function getAll() {
			$this->response->result = $this->db
				->from($this->table)
				->select("$this->tableS.nombre AS sucursal")
				->leftJoin("$this->tableS on $this->tableS.id = sucursal_id")
				->where("$this->table.status", 1)
				->fetchAll();

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->where("$this->table.status", 1)
				->fetch()
				->Total;

			return $this->response->SetResponse(true);
		}// fin de getAll 

		/*** getSucLista ***/
		public function getSucLista($sucursal_id, $id) {
			$this->response->result = $this->db
				->from($this->table)
				->where('sucursal_id', $sucursal_id)
				->where('id', $id)
				->where('status', 1)
				->fetch();

			return $this->response->SetResponse(true);
		}// fin de getAll 

		/*  getByOrigen
			*  Regresa todas las listas que tengas como origen el id que recibe
			*/
		public function getByOrigen($id, $sucursal_id) {
			$this->response->result = $this->db
				->from($this->table)
				->where('sucursal_id', $sucursal_id)
				->where('origen', $id)
				->where('status', 1)
				->fetchAll();

			return $this->response->SetResponse(true);
		}// fin

		/* getGeneral
			* recibe id sucursal  
			* Regresa la lists general de la sucursal 
			*/
		public function getGeneral($sucursal_id) {
			$this->response->result = $this->db
				->from($this->table)
				->where('sucursal_id', $sucursal_id)
				->where('status', 1)
				->orderBy('id')
				->fetch();

			return $this->response->SetResponse(true);
		}// fin

		/*** add ***/
		public function add($data) {
			try{
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result!=0)	$this->response->SetResponse(true, 'id del registro: '.$this->response->result);    
				else { $this->response->SetResponse(false, 'no se inserto el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: add model $this->table");
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

				if($this->response->result!=0) { $this->response->SetResponse(true, "id actualizado: $id"); }
				else { $this->response->SetResponse(false, 'no se edito el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model $this->table");
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

				if($this->response->result!=0) { $this->response->SetResponse(true, "id baja: $id");  }
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model $this->table");
			}

			return $this->response;
		}//fin de del

		/*** delBySuc ***/
		public function delBySuc($suc) {
			try{
				$data['status'] = 0;
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('sucursal_id', $suc)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, "id baja: $suc"); }
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model $this->table");
			}

			return $this->response;
		}//fin de delBySuc
	}//fin de prod_lista_precio
?>