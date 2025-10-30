<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class ApartadoModel {
		private $db;
		private $table = 'apartado'; 
		private $response;
		
		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}
	
		/*** get por ID ***/
		public function get($id) {
			$this->response->result = $this->db
				->from($this->table)
				->where('id', $id)
				->fetch();

			if(!$this->response->result)	$this->response->SetResponse(true);
			else { $this->response->SetResponse(false, 'no existe el registro'); }

			return $this->response;
		}// fin de get

		/*** getAll ***/
		public function getAll() {
			$this->response->result = $this->db
				->from($this->table)
				->where('status > 0')
				->fetchAll();

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->where('status > 0')
				->fetch()
				->Total;

			return $this->response->SetResponse(true);
		
		}// fin de getAll 

		/*** sigFolio */
		public function sigFolio() {
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select('COALESCE(MAX(folio)+1, 1) as folio')
				->fetch()
				->folio;

			return $this->response->SetResponse(true);
		}
		// fin de sigFolio

		/*** searchByFolio ***/
		public function searchByFolio($filtro) {
			$this->response->result = $this->db
				->from($this->table)
				->where('folio', $filtro)
				->where('status > 0')
				->fetchAll();

			return $this->response->SetResponse(true);
		}
		// fin de searchByFolio 

		/*** add ***/
		public function add($data) {
			$this->response = new Response();
			try{
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result)	$this->response->SetResponse(true);
				else { $this->response->SetResponse(false, 'no se inserto el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: add model apartado:");
			}
			
			return $this->response;
		}//fin de add

		/*** edit */
		public function edit($data, $id) {
			$this->response = new Response();
			try{
				$this->response->result = $this->db
					->update($this->table, $data, $id)
					->execute();

				if($this->response->result)	$this->response->SetResponse(true);
				else	$this->response->SetResponse(false, 'no se actualizo el registro');

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model apartado");
			}

			return $this->response;
		}//fin de edit

		/*** del */
		public function del($id) {
			$this->response = new Response();
			try {
				$data = ['status' => 0];
				$this->response->result = $this->db
					->update($this->table, $data, $id)
					->execute();

				if($this->response->result)	$this->response->SetResponse(true);
				else { $this->response->SetResponse(false, 'no se elimino el registro'); }

			} catch(\PDOEception $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model apartado");
			}

			return $this->response;
		}
		// fin de del
	}//fin de apartado
?>