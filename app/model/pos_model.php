<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;
	use Envms\FluentPDO\Literal;

class PosModel {
		private $db;
		private $table = 'seg_accion';
		private $tblIng = 'ingreso';
		private $tblEgr = 'egreso';
		private $tblRep = 'reparacion';
		private $tblGar = 'garantia';
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

			if($this->response->result) { return $this->response->SetResponse(true); }
			else { return $this->response->SetResponse(false, 'no existe el registro'); }
		}

        public function getIngresos($ini, $fin) {
            $user = $_SESSION['sucursal'] == 0 ? ' > 0' : ' = '.$_SESSION['usuario']->id;
            $suc = $_SESSION['sucursal'] == 0 ? ' > 0' : ' = '.$_SESSION['sucursal'];
			$this->response->result = $this->db
				->from($this->tblIng)
                //->select(null)->select("ingreso.*, DATE_FORMAT(ingreso.fecha, '%H:%i') AS hora")
                ->select(null)->select("ingreso.*, sucursal.nombre AS sucursal, usuario.nombre AS usuario")
				// ->where('usuario_id'. $user)
				->where('sucursal_id'. $suc)
                ->where('ingreso.status', 1)
				//->where("DATE_FORMAT(fecha,'%Y-%m-%d') = DATE_FORMAT(NOW(),'%Y-%m-%d')")
				->where("DATE_FORMAT(fecha,'%Y-%m-%d') BETWEEN DATE_FORMAT('$ini','%Y-%m-%d') AND DATE_FORMAT('$fin','%Y-%m-%d')")
				->fetchAll();

			if($this->response->result) { return $this->response->SetResponse(true); }
			else { return $this->response->SetResponse(false, 'no existe el registro'); }
        }

        public function addIngreso($data) {
            $data['usuario_id'] = $_SESSION['usuario']->id;
            $data['sucursal_id'] = $_SESSION['sucursal']>0?$_SESSION['sucursal']:1;
            $data['fecha'] = new Literal('NOW()');
			try {
				$this->response->result = $this->db
					->insertInto($this->tblIng, $data)
					->execute();

				if($this->response->result != 0) { $this->response->SetResponse(true, 'id del registro: '.$this->response->result); }
				else { $this->response->SetResponse(false, 'no se inserto el registro'); }
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: add model $this->tblIng");
			}

			return $this->response;
		}

		public function delIngreso($id) {
			try{
				$data['status'] = 0;
				$this->response->result = $this->db
					->update($this->tblIng, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, "id baja: $id"); }
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }
			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model $this->table");
			}

			return $this->response;
	    }

        public function getEgresos($ini, $fin) {
            $user = $_SESSION['sucursal'] == 0 ? ' > 0' : ' = '.$_SESSION['usuario']->id;
            $suc = $_SESSION['sucursal'] == 0 ? ' > 0' : ' = '.$_SESSION['sucursal'];
			$this->response->result = $this->db
				->from($this->tblEgr)
                //->select(null)->select('egreso.*, DATE_FORMAT(ingreso.fecha, "%H:%i") AS hora')
                ->select(null)->select('egreso.*, sucursal.nombre AS sucursal, usuario.nombre AS usuario')
				// ->where('usuario_id'. $user)
				->where('sucursal_id'. $suc)
                ->where('egreso.status', 1)
				//->where("DATE_FORMAT(fecha,'%Y-%m-%d') = DATE_FORMAT(NOW(),'%Y-%m-%d')")
				->where("DATE_FORMAT(fecha,'%Y-%m-%d') BETWEEN DATE_FORMAT('$ini','%Y-%m-%d') AND DATE_FORMAT('$fin','%Y-%m-%d')")
				->fetchAll();

			if($this->response->result) { return $this->response->SetResponse(true); }
			else { return $this->response->SetResponse(false, 'no existe el registro'); }
		}

        public function addEgreso($data) {
            $data['usuario_id'] = $_SESSION['usuario']->id;
            $data['sucursal_id'] = $_SESSION['sucursal']>0?$_SESSION['sucursal']:1;
            $data['fecha'] = new Literal('NOW()');
			try {
				$this->response->result = $this->db
					->insertInto($this->tblEgr, $data)
					->execute();

				if($this->response->result != 0) { $this->response->SetResponse(true, 'id del registro: '.$this->response->result); }
				else { $this->response->SetResponse(false, 'no se inserto el registro'); }
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: add model $this->tblIng");
			}

			return $this->response;
		}

		public function delEgreso($id) {
			try{
				$data['status'] = 0;
				$this->response->result = $this->db
					->update($this->tblEgr, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, "id baja: $id"); }
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }
			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model $this->table");
			}

			return $this->response;
	    }

        
        public function getReparaciones($suc=0) {
            $edo = $suc!=0?"estado != 'Entregado'":'1=1';
            $suc = $suc!=0?'sucursal_id='.$suc:'1=1';
			$this->response->result = $this->db
				->from($this->tblRep)
                ->select('reparacion.*, sucursal.nombre AS sucursal')
				->where($suc)
				->where($edo)
                ->where('reparacion.status', 1)
                ->orderBy('fecha DESC')
				->fetchAll();

			if($this->response->result) { return $this->response->SetResponse(true); }
			else { return $this->response->SetResponse(false, 'no existe el registro'); }
        }

		public function getReparacion($id) {
			$res = $this->db
				->from($this->tblRep)
                ->where('id', $id)
				->fetch();

			return $res;
        }

		public function getReparacionesDate($ini, $fin) {
			$this->response->result = $this->db
				->from($this->tblRep)
                ->select('reparacion.*, sucursal.nombre AS sucursal')
				->where("DATE_FORMAT(fecha,'%Y-%m-%d') BETWEEN '$ini' AND '$fin'")
                ->where('reparacion.status', 1)
                ->orderBy('fecha DESC')
				->fetchAll();

			if($this->response->result) { return $this->response->SetResponse(true); }
			else { return $this->response->SetResponse(false, 'no existe el registro'); }
        }

        public function addReparacion($data) {
            $data['usuario_id'] = $_SESSION['usuario']->id;
            $data['sucursal_id'] = $_SESSION['sucursal']>0?$_SESSION['sucursal']:4;
            $data['fecha'] = new Literal('NOW()');
            $data['estado'] = 'En Sucursal';
			try {
				$this->response->result = $this->db
					->insertInto($this->tblRep, $data)
					->execute();

				if($this->response->result > 0) { $this->response->SetResponse(true, 'id del registro: '.$this->response->result); }
				else { $this->response->SetResponse(false, 'no se inserto el registro'); }
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: add model $this->tblRep");
			}
			return $this->response;
		}

        public function setReparacion($data, $id) {
			try{
				$this->response->result = $this->db
					->update($this->tblRep, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, "id actualizado: $id"); }
				else { $this->response->SetResponse(false, 'no se edito el registro'); }
			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model $this->tblRep");
			}

			return $this->response;
		}

		public function deliveryReparacion($id) {
			$data = array('estado' => new Literal('CONCAT("Entregado | ", estado)'));
			try{
				$this->response->result = $this->db
					->update($this->tblRep, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, "id actualizado: $id"); }
				else { $this->response->SetResponse(false, 'no se edito el registro'); }
			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model $this->tblRep");
			}

			return $this->response;
		}

		public function delReparacion($id) {
			try{
				$data['status'] = 0;
				$this->response->result = $this->db
					->update($this->tblRep, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, "id baja: $id"); }
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }
			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model $this->tblRep");
			}
			return $this->response;
	    }


		public function getGarantias($suc=0) {
            $edo = $suc!=0?"estado != 'Entregado'":'1=1';
            $suc = $suc!=0?$this->tblGar.'.sucursal_id='.$suc:'1=1';
			$this->response->result = $this->db
				->from($this->tblGar)
                ->select('garantia.*, sucursal.nombre AS sucursal, venta.fecha AS fecha_venta, CONCAT(producto.nombre," ",producto.modelo) AS producto, producto.marca_id')
				->where($suc)
				->where($edo)
                ->where('garantia.status', 1)
                ->orderBy('garantia.fecha DESC')
				->fetchAll();

			if($this->response->result) { return $this->response->SetResponse(true); }
			else { return $this->response->SetResponse(false, 'no existe el registro'); }
        }

		public function getGarantiasDate($ini, $fin) {
			$this->response->result = $this->db
				->from($this->tblGar)
                //->select('garantia.*, sucursal.nombre AS sucursal')
				->select('garantia.*, sucursal.nombre AS sucursal, venta.fecha AS fecha_venta, CONCAT(producto.nombre," ",producto.modelo) AS producto, producto.marca_id')
				->where("DATE_FORMAT(garantia.fecha,'%Y-%m-%d') BETWEEN '$ini' AND '$fin'")
                ->where('garantia.status', 1)
                ->orderBy('garantia.fecha DESC')
				->fetchAll();

			if($this->response->result) { return $this->response->SetResponse(true); }
			else { return $this->response->SetResponse(false, 'no existe el registro'); }
        }

        public function addGarantia($data) {
            $data['usuario_id'] = $_SESSION['usuario']->id;
            $data['sucursal_id'] = $_SESSION['sucursal']>0?$_SESSION['sucursal']:4;
            $data['fecha'] = new Literal('NOW()');
            $data['estado'] = 'En Sucursal';
			try {
				$this->response->result = $this->db
					->insertInto($this->tblGar, $data)
					->execute();

				if($this->response->result > 0) { $this->response->SetResponse(true, 'id del registro: '.$this->response->result); }
				else { $this->response->SetResponse(false, 'no se inserto el registro'); }
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: add model $this->tblGar");
			}
			return $this->response;
		}

        public function setGarantia($data, $id) {
			try{
				$this->response->result = $this->db
					->update($this->tblGar, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, "id actualizado: $id"); }
				else { $this->response->SetResponse(false, 'no se edito el registro'); }
			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model $this->tblGar");
			}

			return $this->response;
		}

		public function delGarantia($id) {
			try{
				$data['status'] = 0;
				$this->response->result = $this->db
					->update($this->tblGar, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, "id baja: $id"); }
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }
			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model $this->tblGar");
			}
			return $this->response;
	    }



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
		}
	
		public function getByModulo($seg_modulo_id) {
			$this->response->result = $this->db
				->from($this->table)
				->where('seg_modulo_id', $seg_modulo_id)
				->where('status', 1)
				->fetchAll();

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->where('seg_modulo_id', $seg_modulo_id)
				->where('status', 1)
				->fetch()
				->Total;

			return $this->response->SetResponse(true);
		}

		public function add($data) {
			try {
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result != 0) { $this->response->SetResponse(true, 'id del registro: '.$this->response->result); }
				else { $this->response->SetResponse(false, 'no se inserto el registro'); }
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: add model $this->table");
			}

			return $this->response;
		}

		public function edit($data, $id) {
			try{
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, "id actualizado: $id"); }
				else { $this->response->SetResponse(false, 'no se edito el registro'); }
			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model $this->table");
			}

			return $this->response;
		}

		public function del($id) {
			try{
				$data['status'] = 0;
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, "id baja: $id"); }
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }
			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model $this->table");
			}

			return $this->response;
	    }
	}
?>