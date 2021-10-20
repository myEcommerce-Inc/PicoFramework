<?php

	require('PicoDatabase.php');

	$d = new PicoDatabase('localhost', 'root');

	$q = $d
		->select('f1', 'f2')->select('f3')->select(array('f4', 'f5'))->select('?@', 'f6')->select('?@', array('f7', 'f8'))
		->from('table1')->from('table2')
		->helloWorld()
		->leftJoin('table3')
		->on('a = ?_', 1)
		->on('b = ?*', 2)
		->where('x', 'y')
		->where('x=?_ OR y=?* OR z LIKE \'??\' OR j LIKE ?_ OR k LIKE ?_ OR a IN (?_)', 1, 2, '%??%/\"/\%??%', '/\/\1\'1"1\/\/', array(1, 2, 3))
		->where('?@ = ?@', 'n', 'm')
		->where(array('x = ?_', 'y = ?_'), 10)
		->where(array(
			'a = ?_' => 11,
			'b =' => 22,
			'c' => 33,
			's = ?_ OR s = ?_ OR s = ?_' => array(44, 55 ,66),
			's' => array(44, 55 ,66),
			's IN' => array(44, 55 ,66),
			's IN (?_) OR t IN (?_)' => array(array(44, 55 ,66), array(77, 88 ,99)),
		))
		->set('?_', array('first'=>1, 'second'=>2, 'third'=>3))
		->set('?_', array('first'=>1, 'second'=>2, 'third'=>3))
		->set(array('first'=>1, 'second'=>2, 'third'=>3))
		->set('dt = ?*', 'NOW()')
		->orderBy('f9')
		->limit(1, 2)
		->onDuplicateKeyUpdate(array('qqq'=>'1','www'=>'2'))
	;

	var_dump(strval($q));

	var_dump(strval($d->buildQuery()->optimizeTable('bla')));

	//$q = $d->select('?* AS ?@', 1, 'f1')->select('?_ AS ?@', 2, 'f2')->unionSelect('3 AS f1', '4 AS f2')->nop()->unionSelect('5 AS f1', '6 AS f2');
	//var_dump(strval($q));
	//var_dump($q->fetchAll());
	//$q = $d->query('SELECT ?* AS ?@, ?_ AS ?@  UNION  SELECT 3 AS f1, 4 AS f2  UNION  SELECT 5 AS f1, 6 AS f2', 1, 'f1', 2, 'f2');
	//var_dump($q->fetch());
	//var_dump($q->fetch_row());
	//var_dump($q->fetch_assoc());
	/*foreach($q as $q1) {
		var_dump($q1);
	}*/
	//var_dump($q->fetchAll());
	//var_dump($q->fetchAll('f1'));
	//var_dump($q->fetchCol());
	//var_dump($q->fetchCol('f2'));
	//var_dump($q->fetchCol('f1', 'f2'));
	//var_dump($q->fetchCol('', 'f2'));
	//var_dump($q->fetchVal());
	//var_dump($q->fetchVal('f2'));
	//var_dump($q->fetchVal('bla'));

	var_dump(strval($d->nop('1')->or('2')));

	var_dump(strval($d->insertInto('table', array('first'=>1, 'second'=>2, 'third'=>3))));

	var_dump(strval($d->update('table', array('first'=>1, 'second'=>2, 'third'=>3))));
