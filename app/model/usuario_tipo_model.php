<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class UsuarioTipoModel {
		private $db;
		private $table = 'usuario_tipo';
		private $response;

		public function __CONSTRUCT($db) {
			require_once './core/defines.php';
			$this->db = $db;
			$this->response = new Response();
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

		public function find($busqueda) {
			$busqueda = $busqueda==0? "_": $busqueda;
			$this->response->result = $this->db
				->from($this->table)
				->where("nombre LIKE '%$busqueda%'")
				->where("status", 1)
				->fetchAll();

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select("COUNT(*) AS total")
				->where("nombre LIKE '%$busqueda%'")
				->where("status", 1)
				->fetch()
				->total;

			return $this->response->SetResponse(true);
		}

		public function getAll($pagina=0, $limite=0, $busqueda=0) {
			$busqueda = $busqueda==0? "_": $busqueda;
			if($limite == 0) {
				$this->response->result = $this->db
					->from($this->table)
					->where("nombre LIKE '%$busqueda%'")
					->where("status", 1)
					->fetchAll();
			} else {
				$inicial = $pagina * $limite;
				$this->response->result = $this->db
					->from($this->table)
					->where("nombre LIKE '%$busqueda%'")
					->where("status", 1)
					->limit("$inicial, $limite")
					->fetchAll();
			}

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) AS Total')
				->where("nombre LIKE '%$busqueda%'")
				->where("status", 1)
				->fetch()->Total;

			return $this->response->SetResponse(true);
		}
	}
?>