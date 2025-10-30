<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class IngresoModel {
		private $db;
		private $table = 'ingreso'; 
		private $response;
		
		public function __CONSTRUCT($db) {
			require_once './core/defines.php';
			$this->db = $db;
			$this->response = new Response();
		}

		public function getCorte($sucursal_id, $fecha=null) {
			if($fecha == null) { $fecha = date('Y-m-d'); }
			$this->response->result = $this->db
				->from($this->table)
				->select(NULL)->select("sum(importe) AS importe")
				->where("date_format(fecha, '%Y-%m-%d') = '$fecha'")
				->where("status", 1)
				->where("sucursal_id", $sucursal_id)
				->fetch()
				->importe;

			if($this->response->result == null) { $this->response->result = 0; }
			return $this->response->SetResponse(true);
		}
	}
?>