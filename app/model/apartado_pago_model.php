<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class ApartadoPagoModel {
		private $db;
		private $table = 'apartado_pago'; 
		private $tableA = 'apartado'; 
		private $tableT = 'tipo_pago'; 
		private $tableV = 'venta'; 
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

			if(!$this->response->result) { $this->response->SetResponse(false,'no existe el registro'); }
			else	$this->response->SetResponse(true,' ');

			return $this->response;
		}// fin de get

		public function getByApartado($apartado_id, $status=0) {
			$this->response->result = $this->db
				->from($this->table)
				->select("COALESCE(orden_id, '') AS orden_id, COALESCE(comentario, '') AS comentario, $this->tableT.nombre as tipo_pago, tiene_comprobante, es_automatico")
				->leftJoin("$this->tableT on $this->tableT.id = tipo_pago_id")
				->where('apartado_id', $apartado_id)
				->where(intval($status)==0? "$this->table.status > 0": "$this->table.status = $status")
				->fetchAll();

			return $this->response->SetResponse(true);
		}

		public function getPaymentsSum($apartado_id) {
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select('COALESCE(SUM(importe), 0) AS total')
				->where('apartado_id', $apartado_id)
				->where('status', 1)
				->fetch()
				->total;

			return $this->response->SetResponse(true);
		}

		/*** getAll ***/
		public function getAll($pagina, $limite, $apartado_id, $inicio, $final, $cliente_id, $status) {
			$offset = $pagina * $limite;

			$this->response->result = $this->db
				->from($this->table)
				->select("$this->tableA.*, $this->table.*, coalesce(comentario, '') AS comentario, $this->tableT.nombre AS tipo_pago, $this->tableT.tiene_comprobante, $this->tableT.es_automatico")
				->leftJoin("$this->tableA on $this->tableA.id = apartado_id")
				->leftJoin("$this->tableT on $this->tableT.id = tipo_pago_id")
				->where("apartado_id ".($apartado_id==0? ">": "=")." $apartado_id")
				->where("cast($this->table.fecha AS date) >= '$inicio'")
				->where("cast($this->table.fecha AS date) <= '$final'")
				->where("cliente_id ".($cliente_id==0? ">": "=")." $cliente_id")
				->where("$this->table.status ".($status==0? ">": "=")." $status")
				->where("$this->tableA.status ", 2)
				->fetchAll();

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select("COUNT(*) AS Total")
				->leftJoin("$this->tableA on $this->tableA.id = apartado_id")
				->where("apartado_id ".($apartado_id==0? ">": "=")." $apartado_id")
				->where("cast($this->table.fecha AS date) >= '$inicio'")
				->where("cast($this->table.fecha AS date) <= '$final'")
				->where("cliente_id ".($cliente_id==0? ">": "=")." $cliente_id")
				->where("$this->table.status ".($status==0? ">": "=")." $status")
				->where("$this->tableA.status ", 2)
				->fetch()
				->Total;

			return $this->response->SetResponse(true);
		
		}// fin de getAll 

		public function getPosition($apartado_id, $fecha) {
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select("COUNT(*)+1 AS posicion")
				->where("apartado_id", $apartado_id)
				->where("fecha < ?", $fecha)
				->fetch()
				->posicion;

			return $this->response->SetResponse(true);
		}

		public function getVentaAnticipo($apartado_id) {
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select("$this->tableV.*")
				->leftJoin("$this->tableV ON $this->tableV.apartado_pago_id = $this->table.id")
				->where("$this->table.apartado_id", $apartado_id)
				->where("$this->table.status", 1)
				->fetch();

			return $this->response->SetResponse(true);
		}

		/*** sigFolio */
		public function sigFolio() {
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select('COALESCE(MAX(folio)+1, 1) AS folio')
				->fetch()
				->folio;

			return $this->response->SetResponse(true);
		}
		// fin de sigFolio

		/*** add ***/
		public function add($data) {
			try{
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result)	$this->response->SetResponse(true);
				else	$this->response->SetResponse(false, 'no se agrego el registro');

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: add model apartado pago");
			}

			return $this->response;
		}//fin de add

		/*** edit */
		public function edit($data, $id) {
			try{
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				if($this->response->result)	$this->response->SetResponse(true);
				else	$this->response->SetResponse(false, 'no se actualizo el registro');

			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model apartado_pago");
			}

			return $this->response;
		}//fin de edit

		/*** delByApartado */
		public function delByApartado($apartado_id, $status=2) {
			try {
				$data = ['status' => $status];
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('apartado_id', $apartado_id)
					->execute();

				if($this->response->result)	$this->response->SetResponse(true);
				else { $this->response->SetResponse(false, 'no se elimino el registro'); }

			} catch(\PDOEception $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: del model apartado_pago');
			}

			return $this->response;
		}
		// fin de delByApartado

		/*** del */
		public function del($id, $usuario) {
			try {
				$data = $this->get($id)->result;
				$data = [
					'status' => 0,
					'comentario' => "$data->comentario (Eliminado por $usuario)"
				];
				
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				if($this->response->result)	$this->response->SetResponse(true);
				else { $this->response->SetResponse(false, 'no se elimino el registro'); }

			} catch(\PDOEception $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model apartado pago");
			}

			return $this->response;
		}
		// fin de del

		public function saveImgComprobante($file, $id) {
			$directory  = 'data/comprobantes_pago/';
			$extension  = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);

			$basename = "comprobante_$id";
			$filename = sprintf('%s.%0.8s', $basename, $extension);

			$file->moveTo($directory.DIRECTORY_SEPARATOR.$filename);

			$this->response->filename = $filename;
			return $this->response->SetResponse(true);
		}
	}//fin de apartado
?>