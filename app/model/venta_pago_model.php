<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class VentaPagoModel {
		private $db;
		private $table = 'venta_pago'; 
		private $tableT = 'tipo_pago'; 
		private $response;
		
		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}

		public function find($busqueda) {
			$this->response->result = $this->db
				->from($this->table)
				->select(NULL)->select('fecha, tipo, comentario, importe')
				->where("CONCAT_WS(' ', fecha, tipo, comentario, importe) LIKE ?" , "%$busqueda%")
				->fetchAll();

			return $this->response->SetResponse(true);
		}

		public function get($id) {
			$this->response->result = $this->db
				->from($this->table)
				->where('id', $id)
				->fetch();

			if($this->response->result) $this->response->SetResponse(true,' ');
			else { $this->response->SetResponse(false, 'no existe el registro'); }

			return $this->response;
		}

		public function getByVenta($venta_id, $status=0) {
			$this->response->result = $this->db
				->from($this->table)
				->select("COALESCE(orden_id, '') AS orden_id, COALESCE(comentario, '') AS comentario, $this->tableT.nombre as tipo_pago, tiene_comprobante, es_automatico")
				->leftJoin("$this->tableT on $this->tableT.id = tipo_pago_id")
				->where('venta_id', $venta_id)
				->where("$this->table.status".($status==0? ">": "=")." $status")
				->fetchAll();

			$this->response->total = $this->db
				->from($this->table)
				->select(NULL)->select("COUNT(*) AS total")
				->where('venta_id', $venta_id)
				->where("status".($status==0? ">": "=")." $status")
				->fetch()
				->total;

			return $this->response->SetResponse(true);
		}

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
				$this->response->SetResponse(false, 'catch: add model venta_pago');
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
				$this->response->SetResponse(false, 'catch: edit model venta_pago');
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

				if($this->response->result!=0)	$this->response->SetResponse(true, 'id baja: '.$id);
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: del model venta_pago');
			}

			return $this->response;
		}

		public function saveImgComprobante($file, $id) {
			$directory  = 'data/comprobantes_pago/';
			$extension  = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);

			$basename = "venta_comprobante_$id";
			$filename = sprintf('%s.%0.8s', $basename, $extension);

			$file->moveTo($directory.DIRECTORY_SEPARATOR.$filename);

			$this->response->filename = $filename;
			return $this->response->SetResponse(true);
		}

		public function getImportePagado($venta_id) {
			$pagos = $this->getByVenta($venta_id, 1)->result;
			$total = 0;
			foreach($pagos as $pago) {
				$total += $pago->importe;
			}

			return $total;
		}
	}
?>