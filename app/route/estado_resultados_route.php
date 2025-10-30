<?php
	use App\Lib\Response,
		Envms\FluentPDO\Literal;
	use App\Lib\MiddlewareToken;

	$app->group('/estado_resultados/', function() {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de estado_resultados');
		});
		
		$this->get('get/{id_estado_resultados}', function($request, $response, $arguments) {
			return $response->withJson($this->model->estado_resultados->get($arguments['id_estado_resultados']));
		});

		$this->get('getAll/{page}/{limit}[/{since}/{to}]', function($request, $response, $arguments) {
			$arguments['since'] = isset($arguments['since'])? $arguments['since']: null;
			$arguments['to'] = isset($arguments['to'])? $arguments['to']: null;

			return $response->withJson($this->model->estado_resultados->getAll($arguments['page'], $arguments['limit'], $arguments['since'], $arguments['to']));
		});

		$this->post('add/', function($request, $response, $arguments) {
			if(!isset($_SESSION['usuario'])) session_start();
			date_default_timezone_set('America/Mexico_City');
			$parsedBody = $request->getParsedBody();
			$parsedBody['fk_empleado'] = $_SESSION['usuario']->id_usuario;
			$parsedBody['editado'] = date('Y-m-d H:i:s');

			return $response->withJson($this->model->estado_resultados->add($parsedBody));
		});

		$this->put('edit/{id_estado_resultados}', function($request, $response, $arguments) {
			if(!isset($_SESSION['usuario'])) session_start();
			date_default_timezone_set('America/Mexico_City');
			$parsedBody = $request->getParsedBody();
			$parsedBody['fk_empleado'] = $_SESSION['usuario']->id_usuario;
			$parsedBody['editado'] = date('Y-m-d');

			return $response->withJson($this->model->estado_resultados->edit($parsedBody, $arguments['id_estado_resultados']));
		});

		$this->put('del/{id_estado_resultados}', function($request, $response, $arguments) {
			return $response->withJson($this->model->estado_resultados->del($arguments['id_estado_resultados']));
		});

		$this->get('getByDate/{year}/{month}', function($request, $response, $arguments) {
			return $response->withJson($this->model->estado_resultados->getByDate($arguments['year'], $arguments['month']));
		});

		$this->get('exportToExcel/[{start}/{end}]', function($request, $response, $arguments) {
			if(!isset($_SESSION)) { session_start(); }
			if(isset($_SESSION['usuario'])) {
				$params = array('vista' => 'resultados');
				try{
					$modulo = 4;
					$user = $_SESSION['usuario']->id_usuario;
					$permisos = getPermisos($this->model->usuario->getAcciones($user, $modulo));
					$start = isset($arguments['start'])? $arguments['start']: date('m-Y');
					$end = isset($arguments['end'])? $arguments['end']: date('m-Y');
					$data = $this->model->estado_resultados->getAll(0, 0, $start, $end);
					$resultados = [];
					foreach($data->result as $row) {
						$resultados[str_pad($row->mes, 2, '0', STR_PAD_LEFT)."/$row->anio"] = $row;
					}

					$params = array('vista' => 'rptResultados', 'permisos' => $permisos, 'start' => $start, 'end' => $end, 'resultados' => $resultados, 'acumulado' => $data->accumulated);
					return $this->renderer->render($response, 'rptEdoResultados.phtml', $params);
				} catch (Throwable $t) { // PHP 7+
					print_r($t);
					echo '<br>throw 7+'; exit();
					return $this->renderer->render($response, '404.phtml', $params);
				} catch (Exception $e) { // PHP < 7
					return $this->renderer->render($response, '404.phtml', $params);
				}
			}else{
				return $this->view->render($response, 'login.phtml', $arguments);
			}
		});
	})->add( new MiddlewareToken() );
?>