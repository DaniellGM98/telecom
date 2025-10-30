<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class ProductoModel {
		private $db;
		private $table = 'producto';
		private $tableCat = 'prod_categoria';
		private $tableSub = 'prod_subcategoria';
		private $tableM = 'marca';
		private $tableE = 'prod_entrada';
		private $tableEDet = 'prod_entrada_detalle';
		private $tableP = 'prod_precio';
		private $tableL = 'prod_lista_precio';
		private $tableEv = 'prod_evento';
		private $tableTEv = 'evento';
		private $tableK = 'prod_kardex';
		private $tableV = 'venta';
		private $tableD = 'venta_detalle';
		private $tableR = 'resena';
		private $tableI = 'prod_imagen';
		private $tableS = 'sucursal';
		private $response;
		
		public function __CONSTRUCT($db) {
			require_once './core/defines.php';
			$this->db = $db;
			$this->response = new Response();
		}

		public function findFromAllSucursales($busqueda) {
			$this->response->result = $this->db->getPdo()->query(
				"SELECT
					$this->table.id,
					$this->table.sku AS codigo,
					UPPER(CONCAT_WS(' ', $this->tableCat.nombre, $this->tableM.nombre, $this->table.nombre, modelo, descripcion)) AS producto,
					prod_categoria_id,
					$this->tableCat.nombre AS categoria,
					marca_id,
					$this->tableM.nombre AS marca,
					stock,
					$this->tableK.sucursal_id,
					$this->tableS.nombre,
					(SELECT final FROM $this->tableK kardex WHERE id = (SELECT MAX(id) FROM $this->tableK stock WHERE stock.producto_id = $this->tableK.producto_id AND stock.sucursal_id = $this->tableK.sucursal_id AND stock.status = 1 ORDER BY id DESC)) AS final,
					precio
				FROM $this->tableK
				LEFT JOIN $this->table on $this->tableK.producto_id = $this->table.id
				LEFT JOIN $this->tableS on $this->tableK.sucursal_id = $this->tableS.id
				LEFT JOIN $this->tableCat on prod_categoria_id = $this->tableCat.id
				LEFT JOIN $this->tableM on marca_id = $this->tableM.id
				LEFT JOIN $this->tableL on $this->tableL.sucursal_id = $this->tableK.sucursal_id AND $this->tableL.origen = 0
				LEFT JOIN $this->tableP on $this->tableL.id = lista_precio_id AND $this->tableP.producto_id = $this->table.id
				WHERE
					CONCAT_WS(' ', CASE WHEN producto.sku IS NOT NUll THEN CONCAT($this->table.sku, ' ') ELSE '' END, $this->tableCat.nombre, $this->tableM.nombre, CONCAT_WS(' ', $this->table.nombre, modelo, descripcion)) LIKE '%$busqueda%' AND
					$this->table.status = 1
				GROUP BY $this->tableK.producto_id, $this->tableK.sucursal_id;"
			)->fetchAll();

			return $this->response->SetResponse(true);
		}

		public function findProducto($busqueda, $sucursal_id){
			if($sucursal_id == 0){$sucursal_id = 8 ;}
			$this->response->result = $this->db->getPdo()->query(
				"SELECT  
				producto.id AS id, UPPER(CONCAT_WS(' ', marca.nombre, producto.nombre,producto.modelo)) AS producto,
				prod_categoria.nombre AS categoria,
				(SELECT precio FROM prod_precio WHERE lista_precio_id = $sucursal_id  AND prod_precio.producto_id = producto.id)AS precio,
				stock
				FROM producto
				LEFT JOIN marca ON marca_id = marca.id
				INNER JOIN prod_categoria ON prod_categoria_id = prod_categoria.id
				WHERE producto.status = 1 AND CONCAT_WS(' ',producto.nombre,producto.modelo,producto.descripcion,CONCAT_WS(' ',prod_categoria.nombre, marca.nombre))LIKE '%$busqueda%';"
			)->fetchAll();
			return $this->response->SetResponse(true);
			}

		public function stockProduct($producto_id){
			$this->response->result = $this->db->getPdo()->query(
				"SELECT sucursal_id,
				(SELECT final FROM prod_kardex AS pk WHERE pk.producto_id = producto.id AND pk.sucursal_id = prod_kardex.sucursal_id ORDER BY pk.id DESC LIMIT 1) AS final
				FROM `prod_kardex`
				INNER JOIN producto on prod_kardex.producto_id = producto.id
				WHERE producto.id = $producto_id
				GROUP BY prod_kardex.producto_id, prod_kardex.sucursal_id ;"
			)->fetchAll();
			return $this->response->SetResponse(true);
		}
		public function find($busqueda, $sucursal_id=0, $stockMin=0, $prod_categoria_id=0) {
			$producto = intval($prod_categoria_id)==0? "CONCAT_WS(' ', $this->tableCat.nombre, $this->tableM.nombre, $this->table.nombre, modelo, descripcion)": "CONCAT_WS(' ', $this->tableM.nombre, $this->table.nombre, modelo, descripcion)";
			$prod_categoria_id = intval($prod_categoria_id)>0? "$this->tableCat.id = $prod_categoria_id": "TRUE";
			if(intval($sucursal_id)>0 && intval($stockMin)>0) {
				$this->response->result = $this->db->getPdo()->query(
					"SELECT
						$this->table.id,
						UPPER($producto) AS producto,
						prod_categoria_id,
						sku_nombre
					FROM $this->table
					LEFT JOIN $this->tableCat ON $this->tableCat.id = prod_categoria_id
					LEFT JOIN $this->tableM ON $this->tableM.id = marca_id
					WHERE
						(SELECT final FROM $this->tableK WHERE id = (SELECT MAX(id) FROM $this->tableK WHERE producto_id = $this->table.id AND sucursal_id = $sucursal_id AND status = 1)) >= $stockMin AND
						$producto LIKE '%$busqueda%' AND
						$prod_categoria_id AND
						$this->table.status = 1
				")->fetchAll();
			} else {
				$this->response->result = $this->db
					->from($this->table)
					->select(NULL)->select("$this->table.id, UPPER($producto) AS producto, prod_categoria_id, IFNULL(sku_nombre,'') AS sku_nombre")
					->leftJoin("$this->tableCat ON $this->tableCat.id = prod_categoria_id")
					->leftJoin("$this->tableM ON $this->tableM.id = marca_id")
					->where("$producto LIKE ?", "%$busqueda%")
					->where($prod_categoria_id)
					->where("$this->table.status", 1)
					->fetchAll();
			}

			return $this->response->SetResponse(true);
		}

		public function get($id) {
			$this->response->result = $this->db
				->from($this->table)
				->select("UPPER(CONCAT_WS(' ', $this->tableCat.nombre, $this->tableM.nombre, $this->table.nombre, modelo, descripcion)) AS producto")
				->leftJoin("$this->tableCat ON $this->tableCat.id = prod_categoria_id")
				->leftJoin("$this->tableM ON $this->tableM.id = marca_id")
				->where("$this->table.id", $id)
				->fetch();

			if($this->response->result) { $this->response->SetResponse(true); }
			else { $this->response->SetResponse(false,'no existe el registro'); }

			return $this->response;
		}

		public function getByMD5($md5Value, $campo='nombre') {
			$this->response->result = $this->db
				->from($this->table)
				->where("MD5(LOWER($campo))", $md5Value)
				->where('status', 1)
				->fetchAll();
			
			$this->response->total = $this->db
				->from($this->table)
				->select(NULL)->select('COUNT(*) AS total')
				->where("MD5(LOWER($campo))", $md5Value)
				->where('status', 1)
				->fetch()
				->total;

			return $this->response->SetResponse(true);;
		}

		public function getAll($pagina=0, $limite=0, $prod_categoria_id=0, $prod_subcategoria_id=0, $busqueda=0) {
			$busqueda = $busqueda==0? '_': $busqueda;
			if($limite!=0) {
				$inicial = $pagina * $limite;
				$this->response->result = $this->db
					->from($this->table)
					->where(gettype($prod_categoria_id)=='array'? "prod_categoria_id IN (".implode(',', $prod_categoria_id).")": ($prod_categoria_id==0? "true": "prod_categoria_id = $prod_categoria_id"))
					->where(gettype($prod_subcategoria_id)=='array'? "prod_subcategoria_id IN (".implode(',', $prod_subcategoria_id).")": ($prod_subcategoria_id==0? "true": "prod_subcategoria_id = $prod_subcategoria_id"))
					->where("CONCAT_WS(' ', nombre, modelo, sku) LIKE ?", "%$busqueda%")
					->where('status', 1)
					->limit("$inicial, $limite")
					->fetchAll();
			} else {
				$this->response->result = $this->db
					->from($this->table)
					->where(gettype($prod_categoria_id)=='array'? "prod_categoria_id IN (".implode(',', $prod_categoria_id).")": ($prod_categoria_id==0? "true": "prod_categoria_id = $prod_categoria_id"))
					->where(gettype($prod_subcategoria_id)=='array'? "prod_subcategoria_id IN (".implode(',', $prod_subcategoria_id).")": ($prod_subcategoria_id==0? "true": "prod_subcategoria_id = $prod_subcategoria_id"))
					->where("CONCAT_WS(' ', nombre, modelo, sku) LIKE ?", "%$busqueda%")
					->where('status', 1)
					->fetchAll();
			}

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->where(gettype($prod_categoria_id)=='array'? "prod_categoria_id IN (".implode(',', $prod_categoria_id).")": ($prod_categoria_id==0? "true": "prod_categoria_id = $prod_categoria_id"))
				->where(gettype($prod_subcategoria_id)=='array'? "prod_subcategoria_id IN (".implode(',', $prod_subcategoria_id).")": ($prod_subcategoria_id==0? "true": "prod_subcategoria_id = $prod_subcategoria_id"))
				->where("CONCAT_WS(' ', nombre, modelo, sku) LIKE ?", "%$busqueda%")
				->where('status', 1)
				->fetch()
				->Total;

			return $this->response->SetResponse(true);
		}

		public function getAllbusca($pagina, $limite, $prod_categoria_id, $prod_subcategoria_id, $marca_id, $busqueda) {
			$busqueda = $busqueda=='0'? '_': $busqueda;
			if($limite == 0) {
				$this->response->result = $this->db
					->from($this->table)
					->select("$this->tableCat.nombre AS categoria, $this->tableSub.nombre AS subcategoria, IFNULL($this->tableM.nombre, '-') AS marca, CONCAT($this->table.nombre, ' ', modelo, CASE WHEN tamano IS NOT NULL THEN CONCAT(' ', tamano) ELSE '' END) AS producto")
					->where("CONCAT_WS(' ', $this->table.nombre, modelo, sku) LIKE '%$busqueda%'")
					->where("$this->table.prod_categoria_id".($prod_categoria_id!=0? "=": ">").$prod_categoria_id)
					->where(!is_numeric($prod_subcategoria_id)? "prod_subcategoria_id IN ($prod_subcategoria_id)": ($prod_subcategoria_id!=0? "prod_subcategoria_id = $prod_subcategoria_id": "TRUE"))
					->where($marca_id==0? "true": "marca_id = $marca_id")
					->where("$this->table.status", 1)
					->orderBy("$this->table.nombre ASC")
					->fetchAll();
			} else {
				$inicio = $pagina * $limite;
				$this->response->result = $this->db
					->from($this->table)
					->select("$this->tableCat.nombre AS categoria, $this->tableSub.nombre AS subcategoria, IFNULL($this->tableM.nombre, '-') AS marca, CONCAT($this->table.nombre, ' ', modelo, CASE WHEN tamano IS NOT NULL THEN CONCAT(' ', tamano) ELSE '' END) AS producto")
					->where("CONCAT_WS(' ', $this->table.nombre, modelo, sku) LIKE '%$busqueda%'")
					->where("$this->table.prod_categoria_id".($prod_categoria_id!=0? "=": ">").$prod_categoria_id)
					->where(!is_numeric($prod_subcategoria_id)? "prod_subcategoria_id IN ($prod_subcategoria_id)": ($prod_subcategoria_id!=0? "prod_subcategoria_id = $prod_subcategoria_id": "TRUE"))
					->where($marca_id==0? "true": "marca_id = $marca_id")
					->where("$this->table.status", 1)
					->limit("$inicio, $limite")
					->orderBy("$this->table.nombre ASC")
					->fetchAll();
			}
		
			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->where("CONCAT_WS(' ', nombre, modelo, sku) LIKE '%$busqueda%'")
				->where("prod_categoria_id".($prod_categoria_id!=0? "=": ">").$prod_categoria_id)
				->where(!is_numeric($prod_subcategoria_id)? "prod_subcategoria_id IN ($prod_subcategoria_id)": ($prod_subcategoria_id!=0? "prod_subcategoria_id = $prod_subcategoria_id": "TRUE"))
				->where($marca_id==0? "true": "marca_id = $marca_id")
				->where('status', 1)
				->fetch()
				->Total;
			
		  return $this->response->SetResponse(true);
		}

		public function getAllBuscaTemplate($inicial, $limite, $categoria_id, $subcategoria_id, $marca_id, $sucursal_id, $lista_precio_id, $busqueda, $orden) {
			$busqueda = $busqueda=='0'? '_': $busqueda;
			$stock = $sucursal_id == 4 ? ' AND 1= 1 ' : " AND (IFNULL((SELECT final FROM $this->tableK WHERE producto_id=$this->table.id AND sucursal_id=$sucursal_id AND status=1 ORDER BY id DESC LIMIT 1), 0)) > 0";
			$this->response->result = $this->db->getPdo()->query(
				"SELECT
					$this->table.id,
					sku,
					CONCAT($this->table.nombre, ' ', modelo, CASE WHEN tamano IS NOT NULL THEN CONCAT(' ', tamano) ELSE '' END, CASE WHEN descripcion IS NOT NULL THEN CONCAT(' ', descripcion) ELSE '' END) AS producto,
					$this->table.prod_categoria_id,
					$this->tableCat.nombre AS categoria,
					$this->tableCat.tiene_sku,
					$this->tableCat.sku_nombre,
					$this->table.prod_subcategoria_id,
					$this->tableSub.nombre AS subcategoria,
					IFNULL($this->table.marca_id, 0) AS marca_id,
					IFNULL($this->tableM.nombre, '-') AS marca,
					stock,
					IFNULL((SELECT final FROM $this->tableK WHERE producto_id=$this->table.id AND sucursal_id=$sucursal_id AND status=1 ORDER BY id DESC LIMIT 1), '0') AS stock_sucursal,
					CASE
						WHEN $this->tableL.origen>0 THEN (SELECT precio FROM $this->tableP WHERE $this->tableP.producto_id=$this->table.id AND $this->tableP.lista_precio_id=$this->tableL.origen) * (1-($this->tableL.descuento/100))
						ELSE (SELECT precio FROM $this->tableP WHERE $this->tableP.producto_id=$this->table.id AND $this->tableP.lista_precio_id=$this->tableL.id)
					END AS precio_lista_precio
				FROM $this->table
				LEFT JOIN $this->tableL ON $this->tableL.id=$lista_precio_id AND $this->tableL.sucursal_id=$sucursal_id AND $this->tableL.status=1
				LEFT JOIN $this->tableCat ON $this->tableCat.id=$this->table.prod_categoria_id
				LEFT JOIN $this->tableSub ON $this->tableSub.id=$this->table.prod_subcategoria_id
				LEFT JOIN $this->tableM ON $this->tableM.id=$this->table.marca_id
				WHERE
					".(is_numeric($categoria_id)? (intval($categoria_id)!=0? "$this->table.prod_categoria_id = $categoria_id": "TRUE"): "$this->table.prod_categoria_id IN ($categoria_id)")." AND
					".(is_numeric($subcategoria_id)? (intval($subcategoria_id)!=0? "$this->table.prod_subcategoria_id = $subcategoria_id": "TRUE"): "$this->table.prod_subcategoria_id IN ($subcategoria_id)")." AND
					".(is_numeric($marca_id)? (intval($marca_id)!=0? "$this->table.marca_id = $marca_id": "TRUE"): "$this->table.marca_id IN ($marca_id)")." AND

					CONCAT_WS(' ', sku, $this->table.nombre, modelo, CASE WHEN tamano IS NOT NULL THEN CONCAT(' ', tamano) ELSE '' END, CASE WHEN descripcion IS NOT NULL THEN CONCAT(' ', descripcion) ELSE '' END, $this->tableCat.nombre, $this->tableSub.nombre, IFNULL($this->tableM.nombre, '-')) 
					LIKE '%$busqueda%' AND
					$this->table.status = 1 
					$stock
				ORDER BY $orden
				LIMIT $inicial, $limite"
			)->fetchAll();

			$this->response->total =  $this->db
				->from($this->table)
				->select(NULL)->select('COUNT(*) AS total')
				->where('status', 1)
				->fetch()->total;

			$this->response->filtered = $this->db->getPdo()->query(
				"SELECT COUNT(*) AS total 
				FROM $this->table
				LEFT JOIN $this->tableL ON $this->tableL.id=$lista_precio_id AND $this->tableL.sucursal_id=$sucursal_id AND $this->tableL.status=1
				LEFT JOIN $this->tableCat ON $this->tableCat.id=$this->table.prod_categoria_id
				LEFT JOIN $this->tableSub ON $this->tableSub.id=$this->table.prod_subcategoria_id
				LEFT JOIN $this->tableM ON $this->tableM.id=$this->table.marca_id
				WHERE
					".(is_numeric($categoria_id)? (intval($categoria_id)!=0? "$this->table.prod_categoria_id = $categoria_id": "TRUE"): "$this->table.prod_categoria_id IN ($categoria_id)")." AND
					".(is_numeric($subcategoria_id)? (intval($subcategoria_id)!=0? "$this->table.prod_subcategoria_id = $subcategoria_id": "TRUE"): "$this->table.prod_subcategoria_id IN ($subcategoria_id)")." AND
					".(is_numeric($marca_id)? (intval($marca_id)!=0? "$this->table.marca_id = $marca_id": "TRUE"): "$this->table.marca_id IN ($marca_id)")." AND
					CONCAT_WS(' ', sku, $this->table.nombre, modelo, CASE WHEN tamano IS NOT NULL THEN CONCAT(' ', tamano) ELSE '' END, $this->tableCat.nombre, $this->tableSub.nombre, IFNULL($this->tableM.nombre, '-')
					) LIKE '%$busqueda%' AND
					$this->table.status = 1 
					$stock "
			)->fetch()->total;
			
		  return $this->response->SetResponse(true);
		}

		public function getValorStock($suc){
			$query = "SELECT $this->table.id, $this->table.nombre, 
							IFNULL((SELECT final FROM $this->tableK WHERE producto_id=$this->table.id AND sucursal_id=$suc AND status=1 ORDER BY id DESC LIMIT 1), '0') AS stock, 
							
							(SELECT precio FROM $this->tableP WHERE $this->tableP.producto_id=$this->table.id AND $this->tableP.lista_precio_id=$suc) AS precio 

							/* (SELECT costo FROM $this->tableEDet INNER JOIN $this->tableE ON $this->tableEDet.prod_entrada_id = $this->tableE.id WHERE $this->tableEDet.producto_id = $this->table.id and $this->tableE.sucursal_id = $suc ORDER BY $this->tableE.fecha DESC LIMIT 1) AS precio */

							FROM $this->table
								WHERE $this->table.status = 1 AND
									(IFNULL((SELECT final FROM $this->tableK WHERE producto_id=$this->table.id AND sucursal_id=$suc AND status=1 ORDER BY id DESC LIMIT 1), 0)) > 0
								";
			$res = $this->db->getPdo()->query($query)->fetchAll();
			//return array('res' => $res, 'sql' => $query);
			return $res;
		}

		/***
		 * Función para realizar una busqueda de los productos que contengan todos los busquedas solicitados
		 * recibe {pagina} número de página
		 * recibe {limite} limite de registros por cada página
		 * recibe {evento_id} ID del evento, si no se proporciona, o es 0, devolverá todos los productos sin tomar en cuenta si pertenecen a un evento, o no.
		 * recibe {prod_categoria_id} arreglo de las categorías a buscar. Si es 0, se buscarán de todas.
		 * recibe {prod_subcategoria_id} arreglo de las subcategorias a buscar. Si es 0, se buscarán de todas.
		 * recibe {busqueda} puede ser articulo, marca, modelo o tag del producto. Para buscar sin busqueda mandar el caracter '_' como parametro
		 * recibe {precio_min} precio mínimo que tendrán los productos. Si es -1 no se contemplará este busqueda
		 * recibe {precio_max} precio máximo que tendrán los productos. Si es -1 no se contemplará este busqueda
		 * recibe {order} forma en que se ordenarán los productos [1, ascendentemente], [2, descendentemente], [(0, o nada), no ordenar]
		 * recibe {order_field} campo en base al cual se ordenarán los resultados
		 * regresa: objeto con la información de todos los productos
		 */
		public function search($pagina, $limite, $evento_id=0, $prod_categoria_id=0, $prod_subcategoria_id=0, $busqueda='_', $precio_min=-1, $precio_max=-1, $order=0, $order_field='') {
		// 	$sucursal_id					= $_SESSION['id_sucursal'];
		// 	$prod_lista_precio_id			= $_SESSION['id_prod_lista_precio'];
		// 	$prod_lista_precio_id_default	= $_SESSION['id_prod_lista_precio_default'];
			
		// 	$start					= ($pagina - 1) * $limite;
		// 	$evento_id				= $evento_id!='0'? "$this->tableEv.evento_id = $evento_id": "true";
		// 	$prod_categoria_id		= gettype($prod_categoria_id)=='array'? "prod_categoria_id IN (".implode(',', $prod_categoria_id).")": ($prod_categoria_id==0? "TRUE": "prod_categoria_id = $prod_categoria_id");
		// 	$prod_subcategoria_id	= gettype($prod_subcategoria_id)=='array'? "prod_subcategoria_id IN (".implode(',', $prod_subcategoria_id).")": ($prod_subcategoria_id==0? "TRUE": "prod_subcategoria_id = $prod_subcategoria_id");
		// 	$busqueda				= $busqueda!=0? "CONCAT_WS(' ', nombre, modelo, sku) LIKE  '%$busqueda%'": 'TRUE';

		// 	$precio_min = floatval($precio_min);
		// 	$precio_max = floatval($precio_max);
		// 	if($precio_min!=-1 && $precio_max!=-1) {
		// 		$precio = "((prod_precio_evento.precio BETWEEN $precio_min AND $precio_max AND CAST(prod_precio_evento.precio AS SIGNED) > 0) OR (prod_precio_evento.precio IS NULL AND prod_precio.precio BETWEEN $precio_min AND $precio_max AND CAST(prod_precio.precio AS SIGNED) > 0) OR (prod_precio_evento.precio IS NULL AND prod_precio.precio IS NULL AND prod_precio_default.precio BETWEEN $precio_min AND $precio_max AND CAST(prod_precio_default.precio AS SIGNED) > 0))";
		// 	} elseif($precio_min!=-1) {
		// 		$precio = "((prod_precio_evento.precio >= $precio_min AND CAST(prod_precio_evento.precio AS SIGNED) > 0) OR (prod_precio_evento.precio IS NULL AND prod_precio.precio >= $precio_min AND CAST(prod_precio.precio AS SIGNED) > 0) OR (prod_precio_evento.precio IS NULL AND prod_precio.precio IS NULL AND prod_precio_default.precio >= $precio_min AND CAST(prod_precio_default.precio AS SIGNED) > 0))";
		// 	} elseif($precio_max!=-1) {
		// 		$precio = "((prod_precio_evento.precio <= $precio_max AND CAST(prod_precio_evento.precio AS SIGNED) > 0) OR (prod_precio_evento.precio IS NULL AND prod_precio.precio <= $precio_max AND CAST(prod_precio.precio AS SIGNED) > 0) OR (prod_precio_evento.precio IS NULL AND prod_precio.precio IS NULL AND prod_precio_default.precio <= $precio_max AND CAST(prod_precio_default.precio AS SIGNED) > 0))";
		// 	} else {
		// 		$precio = "(CAST(prod_precio_evento.precio AS SIGNED) > 0 OR CAST(prod_precio.precio AS SIGNED) > 0 OR CAST(prod_precio_default.precio AS SIGNED) > 0)";
		// 	}
		// 	$order = ($order_field=='random'? 'RAND()': ($order!=0? "$order_field ".($order=='1'? "ASC": "DESC"): "$this->table.id ASC"));

		// 	$productos = $this->db->getPdo()
		// 		->query(
		// 			"SELECT DISTINCT
		// 				$this->table.id,
		// 				sku,
		// 				CONCAT_WS(' ', nombre, modelo) AS producto,
		// 				REPLACE(REPLACE(CONCAT_WS('_', sku, nombre, modelo), ' ', '_'), '/', '') AS friendly_url,
		// 				prod_categoria_id, 
		// 				prod_subcategoria_id,
		// 				CONVERT(CAST(CONVERT(descripcion USING latin1) AS binary) USING utf8) AS descripcion, 
		// 				CONVERT(CAST(CONVERT(descripcion_larga USING latin1) AS binary) USING utf8) AS descripcion_larga,
		// 				tags,
		// 				case 
		// 					when descuento_tipo = 1 then ifnull(ifnull(prod_precio_evento.precio-$this->table.descuento, ifnull($this->tableP.precio-$this->table.descuento, prod_precio_default.precio-$this->table.descuento)), 0)
		// 					when descuento_tipo != 0 then ifnull(ifnull(prod_precio_evento.precio/(100/(100-$this->table.descuento)), ifnull($this->tableP.precio/(100/(100-$this->table.descuento)), prod_precio_default.precio/(100/(100-$this->table.descuento)))), 0)
		// 					else ifnull(ifnull(prod_precio_evento.precio, ifnull($this->tableP.precio, prod_precio_default.precio)), 0)
		// 				end AS precio,
		// 				ifnull(ifnull((prod_precio_evento.precio*(1-(prod_precio_evento.descuento/100))), ifnull(($this->tableP.precio*(1-($this->tableP.descuento/100))), (prod_precio_default.precio*(1-(prod_precio_default.descuento/100))))), 0) AS precio_original,
		// 				COALESCE($this->table.descuento, 0) AS descuento,
		// 				descuento_tipo,
		// 				minimo,
		// 				maximo,
		// 				ultima_entrada,
		// 				CASE WHEN (SELECT COUNT(*) FROM $this->tableEv WHERE producto_id = $this->table.id AND evento_id = 1)>0 THEN COALESCE(ultima_entrada, '2000-01-01') ELSE '' END AS featured,
		// 				CASE WHEN (SELECT COUNT(*) FROM $this->tableEv WHERE producto_id = $this->table.id AND evento_id = 2)>0 THEN COALESCE(ultima_entrada, '2000-01-01') ELSE '' END AS recien_llegados,
		// 				(SELECT SUM(cantidad) AS best_seller FROM $this->tableD LEFT JOIN $this->tableV ON venta_id = $this->tableV.id WHERE sucursal_id = $sucursal_id AND producto_id = $this->table.id GROUP BY producto_id) AS best_seller,
		// 				COALESCE((SELECT COUNT(*) AS reviews FROM $this->tableR WHERE producto_id = $this->table.id GROUP BY producto_id), 0) AS reviews,
		// 				COALESCE((SELECT COALESCE(AVG(puntuacion)) AS puntuacion FROM $this->tableR WHERE producto_id = $this->table.id GROUP BY producto_id), 0) AS puntuacion,
		// 				$this->tableK.cantidad AS stock
		// 			FROM $this->table
		// 			LEFT JOIN $this->tableEv ON $this->tableEv.producto_id = $this->table.id
		// 			LEFT JOIN (SELECT $this->tableEDet.producto_id, MAX(fecha) AS ultima_entrada FROM $this->tableE 
		// 				LEFT JOIN $this->tableEDet ON prod_entrada_id = $this->tableE.id 
		// 				WHERE $this->tableE.status=1 AND $this->tableEDet.status=1 GROUP BY producto_id) $this->tableE ON $this->tableE.producto_id = $this->table.id
		// 			LEFT JOIN (SELECT producto_id, precio, descuento, $this->tableL.id FROM $this->tableP 
		// 				LEFT JOIN $this->tableL ON $this->tableL.id = $this->tableP.lista_precio_id
		// 				WHERE $this->tableP.lista_precio_id = $prod_lista_precio_id) $this->tableP ON $this->tableP.producto_id = $this->table.id
		// 			LEFT JOIN (SELECT producto_id, precio, descuento, $this->tableL.id FROM $this->tableP 
		// 				LEFT JOIN $this->tableL ON $this->tableL.id = $this->tableP.lista_precio_id
		// 				WHERE $this->tableP.lista_precio_id = $prod_lista_precio_id_default) prod_precio_default ON prod_precio_default.producto_id = $this->table.id
		// 			LEFT JOIN (SELECT $this->tableP.producto_id, precio, descuento, $this->tableL.id FROM $this->tableP 
		// 				LEFT JOIN $this->tableL ON $this->tableL.id = $this->tableP.lista_precio_id
		// 				LEFT JOIN $this->tableEv ON $this->tableP.producto_id = $this->tableEv.producto_id 
		// 				LEFT JOIN (SELECT * FROM $this->tableTEv  WHERE CURDATE() BETWEEN inicio AND fin ORDER BY inicio DESC, fin ASC LIMIT 1) $this->tableTEv ON $this->tableTEv.id = evento_id
		// 				WHERE $this->tableP.lista_precio_id = $this->tableTEv.lista_precio_id
		// 			) prod_precio_evento ON prod_precio_evento.producto_id = $this->table.id
		// 			LEFT JOIN $this->tableK on $this->tableK.producto_id = $this->table.id AND $this->tableK.sucursal_id = $sucursal_id
		// 			WHERE
		// 				$evento_id AND
		// 				$prod_categoria_id AND
		// 				$prod_subcategoria_id AND
		// 				$busqueda AND
		// 				$precio AND
		// 				$this->table.status = 1 AND
		// 				$this->tableK.cantidad > 0
		// 			ORDER BY $order
		// 			LIMIT $start, $limite")
		// 		->fetchAll();

		// 	foreach($productos as &$producto) {
		// 		$producto->images = $this->getImage($producto->id)->result;
		// 	}
		// 	$this->response->result = $productos;

		// 	$this->response->total = $this->db->getPdo()
		// 		->query(
		// 			"SELECT COUNT(DISTINCT $this->table.id) AS total
		// 			FROM $this->table
		// 			LEFT JOIN $this->tableEv ON $this->tableEv.producto_id = $this->table.id
		// 			LEFT JOIN (SELECT producto_id, precio FROM $this->tableP WHERE $this->tableP.lista_precio_id = $prod_lista_precio_id) $this->tableP ON $this->tableP.producto_id = $this->table.id
		// 			LEFT JOIN (SELECT producto_id, precio FROM $this->tableP WHERE $this->tableP.lista_precio_id = $prod_lista_precio_id_default) prod_precio_default ON prod_precio_default.producto_id = $this->table.id
		// 			LEFT JOIN (SELECT $this->tableP.producto_id, precio FROM $this->tableP 
		// 				LEFT JOIN $this->tableEv ON $this->tableP.producto_id = $this->tableEv.producto_id 
		// 				LEFT JOIN (SELECT * FROM $this->tableTEv  WHERE CURDATE() BETWEEN inicio AND fin ORDER BY inicio DESC, fin ASC LIMIT 1) $this->tableTEv ON $this->tableTEv.id = evento_id
		// 				WHERE $this->tableP.lista_precio_id = $this->tableTEv.lista_precio_id
		// 			) prod_precio_evento ON prod_precio_evento.producto_id = $this->table.id
		// 			LEFT JOIN $this->tableK on $this->tableK.producto_id = $this->table.id AND $this->tableK.sucursal_id = $sucursal_id
		// 			WHERE
		// 				$evento_id AND
		// 				$prod_categoria_id AND
		// 				$prod_subcategoria_id AND
		// 				$busqueda AND
		// 				$precio AND
		// 				$this->table.status = 1 AND
		// 				$this->tableK.cantidad > 0;")
		// 		->fetch()->total;

			return $this->response->SetResponse(true);
		}

		public function getImage($producto_id) {
			$this->response->result = $this->db
				->from($this->tableI)
				->where('producto_id', $producto_id)
				->fetchAll();
			
			foreach($this->response->result as &$imagen) {
				if(!file_exists("../public/assets/image/productos/$imagen->nombre")) {
					// unset($imagen);
				}
			}
			
			$this->response->total = count($this->response->result);
			if($this->response->total > 0)	$this->response->SetResponse(true);
			else {
				$this->response->SetResponse(false, 'No existe ninguna imagen de dicho producto');
			}
				
			return $this->response;
		}

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
				$this->response->SetResponse(false, "catch: add model producto: $ex");
			}

			return $this->response;
		}//fin de add

		/*** edit ***/
		public function edit($data, $id) {
			try{
				$querys = [];
				foreach($data as $key => $value) {
					$querys[] = " $key = '$value'";
				}
				$this->response->result = $this->db->getPdo()
					->query(
						"UPDATE $this->table SET
							".(!isset($data['prod_subcategoria_id'])? " prod_subcategoria_id = NULL,": "")."
							".(!isset($data['utilidad'])? " utilidad = NULL, ": "")."
							".implode(', ', $querys)."
						WHERE id = $id;")
					->execute();

				if($this->response->result) { $this->response->SetResponse(true); }
				else { $this->response->SetResponse(false, 'no se actualizo el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model producto");
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

				if($this->response->result)	$this->response->SetResponse(true);
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model producto");
			}

			return $this->response;
		}//fin de del
	}//fin de producto
?>