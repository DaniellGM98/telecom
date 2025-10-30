<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class ProdEntradaDetalleModel {
		private $db;
		private $table = 'prod_entrada_detalle'; 
		private $tableS = 'prod_salida'; 
		private $tableD = 'prod_salida_detalle'; 
		private $tableP = 'producto'; 
		private $tableE = 'prod_entrada'; 
		private $tableSuc = 'sucursal'; 
		private $tableV = 'venta'; 
		private $tableVD = 'venta_detalle'; 
		private $tableA = 'apartado'; 
		private $tableAD = 'apartado_detalle'; 
		private $tableT = 'traspaso'; 
		private $tableTD = 'traspaso_detalle'; 
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

			if($this->response->result) { $this->response->SetResponse(true); }
			else { $this->response->SetResponse(false, 'no existe el registro'); }

			return $this->response;
		}

		public function getByProducto($producto_id, $sucursal_id=0) {
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->table)
				->leftJoin("$this->tableE ON $this->tableE.id = prod_entrada_id AND $this->tableE.status = 1")
				->where(intval($sucursal_id)>0? "sucursal_id = $sucursal_id": true)
				->where('producto_id', $producto_id)
				->where("$this->table.status", 1)
				->fetchAll();

			return $this->response->SetResponse(true);
		}

		public function getExistenciasByProducto($producto_id, $sucursal_id=0) {
			$condicion_sucursal = intval($sucursal_id)!=0? "sucursal_id = $sucursal_id": "TRUE";
			$this->response->result = $this->db->getPdo()->query(
				"SELECT *, (SELECT nombre FROM $this->tableSuc WHERE id = sucursal_id) AS sucursal
				FROM $this->table
				LEFT JOIN (SELECT * FROM $this->tableE WHERE status = 1 AND $condicion_sucursal) $this->tableE ON $this->tableE.id = prod_entrada_id
				WHERE
					producto_id = $producto_id AND
					sku NOT IN (
						SELECT sku
						FROM $this->tableD
						LEFT JOIN (SELECT * FROM $this->tableS WHERE status = 1 AND $condicion_sucursal) $this->tableS ON $this->tableS.id = prod_salida_id
						WHERE
							producto_id = $producto_id AND
							sku IS NOT NULL AND
							$this->tableD.status = 1
					) AND
					$condicion_sucursal AND 
					sku IS NOT NULL"
			)->fetchAll();

			return $this->response->SetResponse(true);
		}

		/*** find ***/
		public function find($busqueda) {
			$this->response->result = $this->db
				->from($this->table)
				->select(NULL)->select('producto_id, prod_entrada_id, cantidad, costo, importe')
				->where("CONCAT_WS(' ', producto_id, prod_entrada_id, cantidad, costo, importe) LIKE ?" , "%$busqueda%")
				->fetchAll();

			return $this->response->SetResponse(true);
		}

		public function buscarPorSku($sku, $categoria_id, $producto_id=0, $sucursal_id=0) {
			$this->response->result = $this->db
				->from($this->table)
				->leftJoin("$this->tableP ON $this->tableP.id = producto_id")
				->leftJoin("$this->tableE ON $this->tableE.id = prod_entrada_id")
				->where("$this->table.sku", $sku)
				->where(intval($producto_id)>0? "producto_id = $producto_id": "TRUE")
				->where(intval($sucursal_id)>0? "sucursal_id = $sucursal_id": "TRUE")
				->where("$this->table.sku", $sku)
				->where("$this->table.status", 1)
				->fetchAll();

			if($this->response->result) { $this->response->SetResponse(true); }
			else { $this->response->SetResponse(false, 'NO existe el registro'); }
			return $this->response;
		}

		public function getDistSku($sku){
			$res = $this->db->from($this->table)
						->select('proveedor.nombre AS proveedor')
						->innerJoin('prod_entrada ON prod_entrada.id = prod_entrada_id')
						->innerJoin('proveedor ON proveedor.id = prod_entrada.proveedor_id')
						->where('sku', $sku)
						->where($this->table.'.status', 1)
						->fetch();
			return $res;
		}

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
		}

		/******************* getByEntrada *******************
		** Metodo que recibe prod_entrada_id el cual compara
		** con prod_entrada_id de la tabla prod_entrada_detalle
		** y regresa todos los campos de la tabla producto 
		** y de la tabla prod_entrada_detalle
		** Joselyn 18/10/19
		****************************************************/
		public function getByEntrada($prod_entrada_id, $agrupar=false) {
			if($agrupar) {
				$this->response->result = $this->db
					->from($this->table)
					->select(null)->select("$this->table.id, UPPER(CONCAT_WS(' ', prod_categoria.nombre, marca.nombre, producto.nombre, modelo, descripcion)) AS producto, SUM(cantidad) AS cantidad, costo, SUM(cantidad)*costo AS importe, IFNULL(GROUP_CONCAT(DISTINCT $this->table.sku SEPARATOR ', '), '') AS codigo, IFNULL(marca.nombre,'') AS marca")
					->innerJoin("producto ON $this->table.producto_id = $this->tableP.id")
					->innerJoin("prod_categoria ON prod_categoria.id = producto.prod_categoria_id")
					->leftJoin("marca ON marca.id = producto.marca_id")
					->where('prod_entrada_id', $prod_entrada_id)
					->where("$this->table.status", 1)
					->groupBy('producto.id, costo')
					->fetchAll();
			} else {
				$this->response->result = $this->db
					->from($this->table)
					->select(null)->select("producto.*, prod_entrada_detalle.*, sku_nombre, prod_categoria.nombre AS categoria, UPPER(CONCAT_WS(' ', prod_categoria.nombre, marca.nombre, producto.nombre, modelo, descripcion)) AS producto, $this->table.sku as codigo, IFNULL(marca.nombre,'') AS marca")
					->innerJoin("producto ON $this->table.producto_id = $this->tableP.id")
					->innerJoin("prod_categoria ON prod_categoria.id = producto.prod_categoria_id")
					->leftJoin("marca ON marca.id = producto.marca_id")
					->where('prod_entrada_id', $prod_entrada_id)
					->where("$this->table.status", 1)
					->fetchAll();

			}

			return $this->response->SetResponse(true);
		}//fin de getByEntrada


		/******************* getByEntradaBusca *******************
		** Metodo que recibe idEntrada el cual compara
		** con prod_entrada_id de la tabla prod_entrada_detalle
		** y regresa todos los campos de la tabla producto 
		** y de la tabla prod_entrada_detalle
		** Si recibe valor en busqueda los filtra por nombre, modelo y sku
		** Autor: Angel Gabriel Ramirez Alva 22/10/19
		****************************************************/
		public function getEntradasByBuscaProd($inicio, $fin, $pagina, $limite, $sucursal_id, $proveedor_id, $busqueda, $cat) {
			$busqueda = $busqueda!='0'? $busqueda: '_';
			$cat = $cat!='0'? 'prod_categoria_id = '.$cat: 'TRUE';
			$pagina = $pagina * $limite;
			$ids = $this->db
				->from($this->table)
				->select(null)->select("SQL_CALC_FOUND_ROWS $this->table.prod_entrada_id AS id_entrada")
				->innerJoin("$this->tableP ON $this->table.producto_id = $this->tableP.id")
				->innerJoin("$this->tableE on $this->table.prod_entrada_id = $this->tableE.id")
				->where("CONCAT_WS(' ', $this->tableP.nombre, $this->tableP.modelo, $this->tableP.sku) LIKE '%$busqueda%'")
				->where("$this->table.status", 1)
				->where($cat)
				//->where("$this->tableE.sucursal_id ".(($sucursal_id==0)? ">": "=")." $sucursal_id")
				//->where("$this->tableE.proveedor_id ".(($proveedor_id==0)? ">": "=")." $proveedor_id")
				->where(intval($sucursal_id)>0? "$this->tableE.sucursal_id = $sucursal_id": "TRUE")
				->where(intval($proveedor_id)>0? "$this->tableE.proveedor_id = $proveedor_id": "TRUE")
				->where("CAST($this->tableE.fecha AS DATE) BETWEEN '$inicio' AND '$fin'")
				->groupBy("$this->table.prod_entrada_id")
				->limit($limite)
				->offset($pagina) 
				->fetchAll();

				$this->response->total = $this->db->getPdo()->query('SELECT FOUND_ROWS()')->fetchColumn();
				$this->response->result = [];
				foreach($ids as $id) { $this->response->result[] = $id->id_entrada; }
				
				return $this->response->SetResponse(true);
		}

		function getNameSucursal($id) {
			return $this->db->getPdo()->query(
				"SELECT nombre FROM $this->tableSuc 
					WHERE id = '$id'
					Limit 1")->fetch();
		}	

		function getSkuApartadoTraspaso($id) {
			return $this->db->getPdo()->query(
				"SELECT DISTINCT sku, sucursal_id as sucursal
				FROM $this->tableAD 
				INNER JOIN $this->tableA ON $this->tableA.id = apartado_id 
				WHERE $this->tableAD.producto_id = $id
				AND $this->tableAD.status = 1 
				AND $this->tableA.status = 2
				UNION
				SELECT DISTINCT sku, origen 
				FROM $this->tableTD 
				INNER JOIN $this->tableT ON traspaso_id = $this->tableT.id 
				WHERE $this->tableTD.producto_id = $id
				AND $this->tableT.status in(1,2)
				")->fetchAll();
		}				

		function getSkuVenta($id) {
			$this->response = new Response();
			$this->response->result = $this->db->getPdo()->query(
				"SELECT DISTINCT sku 
				FROM $this->tableVD 
				INNER JOIN $this->tableV ON venta_id = $this->tableV.id 
				WHERE $this->tableVD.producto_id = $id 
				AND $this->tableVD.status = 1 
				AND $this->tableV.status = 1")->fetchAll();
			return $this->response;
		}

		function getListaSkuDisp2($producto_id, $sucursal_id=0, $arrSku=null, $skuVentas=null) {
			$this->response = new Response();
			$condicionSucursal = intval($sucursal_id)==0? TRUE: "sucursal_id = $sucursal_id";
			$condicionSku = $arrSku==null? TRUE: "sku = '$arrSku'";
			$condicionSkuVentas = $skuVentas==null? TRUE: $skuVentas;
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select("sku,fecha, $this->tableSuc.nombre as sucursal")
				->innerJoin("$this->tableE ON $this->tableE.id = prod_entrada_id")
				->innerJoin("$this->tableSuc ON $this->tableSuc.id = sucursal_id")
				->where($condicionSucursal)
				->where('producto_id', $producto_id)
				->where($condicionSku)
				->where('sku NOT', $condicionSkuVentas)
				->where("$this->table.status", 1)
				->where("$this->tableE.status", 1)
				->orderBy("$this->table.id ASC")
				->fetchAll();
			return $this->response->SetResponse(true);
		}

		function getListaSkuDisp($producto_id, $sucursal_id=0, $arrSku=null) {
			$this->response = new Response();
			$condicionSucursal = intval($sucursal_id)==0? 'TRUE': "sucursal_id = $sucursal_id";
			$condicionSku = $arrSku==null? 'TRUE': "$this->table.sku = '$arrSku'";
			$this->response->result = $this->db->getPdo()->query(
				"SELECT $this->table.sku,fecha,sucursal.nombre as sucursal FROM $this->table INNER JOIN $this->tableE ON $this->tableE.id = prod_entrada_id 
					INNER JOIN sucursal ON sucursal.id = sucursal_id
					INNER JOIN producto ON producto.id = producto_id
					WHERE $condicionSucursal AND producto_id = $producto_id AND $condicionSku 
					AND ($this->table.sku,sucursal_id) NOT IN(
						/*SELECT DISTINCT sku, sucursal_id FROM $this->tableVD LEFT JOIN $this->tableV ON venta_id = $this->tableV.id WHERE $this->tableVD.producto_id = $producto_id AND $this->tableVD.status = 1 AND $this->tableV.status = 1
						UNION */
						SELECT DISTINCT sku, sucursal_id FROM $this->tableAD INNER JOIN $this->tableA ON $this->tableA.id = apartado_id WHERE $this->tableAD.producto_id = $producto_id AND $this->tableAD.status = 1 AND $this->tableA.status = 2
						UNION
						SELECT DISTINCT sku, origen FROM $this->tableTD INNER JOIN $this->tableT ON traspaso_id = $this->tableT.id WHERE $this->tableTD.producto_id = $producto_id AND $this->tableT.status in(1,2) AND $this->tableT.fecha > $this->tableE.fecha) 
						AND $this->table.sku NOT IN (SELECT DISTINCT sku FROM $this->tableVD INNER JOIN $this->tableV ON venta_id = $this->tableV.id WHERE $this->tableVD.producto_id = $producto_id AND $this->tableVD.status = 1 AND $this->tableV.status = 1)
					AND $this->tableP.status = 1
					AND $this->tableSuc.status = 1
					AND $this->table.status = 1 AND $this->tableE.status = 1 ORDER BY $this->table.id asc")->fetchAll();
			return $this->response;
		}

		function getFechaEntradaSkuDisp($sku) {
			return $this->db->getPdo()->query(
				"SELECT fecha FROM $this->table INNER JOIN $this->tableE ON $this->tableE.id = prod_entrada_id 
					WHERE sku = '$sku'
					AND $this->table.status = 1 AND $this->tableE.status = 1 ORDER BY $this->table.id asc Limit 1")->fetch();
		}

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
				$this->response->SetResponse(false, 'catch: add model prod_entrada_detalle');
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

			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: edit model prod_entrada_detalle');
			}

			return $this->response;
		}



		/*** edit by Entrada***/
		public function editByEntrada($data, $prod_entrada_id) {
			try{
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('prod_entrada_id', $prod_entrada_id)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, "id actualizado"); }
				else { $this->response->SetResponse(false, 'no se edito el registro'); }

			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: edit model prod_entrada_detalle');
			}

			return $this->response;
		}


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

			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: del model prod_entrada_detalle');
			}
			
			return $this->response;
		}

		public function delBySku($sku){
			$data['status'] = 0;
			$resultado = $this->db
					->update($this->table, $data)
					->where('sku', $sku)
					->execute();
			return $resultado;
		}
	}//fin de prod_entrada_detalle
?>