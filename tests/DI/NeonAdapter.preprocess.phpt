<?php

declare(strict_types=1);

use Nette\DI\Config\Adapters\NeonAdapter;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\Statement;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


// entities & chaining
$adapter = new NeonAdapter;
$data = $adapter->load(Tester\FileMock::create('
- ent(1)
- ent(2)inner(3, 4)
- ent(3)::inner(5)
', 'neon'));

Assert::equal([
	new Statement('ent', [1]),
	new Statement(
		[
			new Statement('ent', [2]),
			'inner',
		],
		[3, 4]
	),
	new Statement(
		[
			new Statement('ent', [3]),
			'inner',
		],
		[5]
	),
], $data);

Assert::match(<<<'EOD'
# generated by Nette

- ent(1)
- ent(2)::inner(3, 4)
- ent(3)::inner(5)
EOD
, $adapter->dump($data));


// references
$data = [
	'a' => new Statement([new Reference('foo'), 'method'], [new Reference('bar')]),
	'b' => new Statement(new Reference('foo')),
];

Assert::match(<<<'EOD'
# generated by Nette

a: @foo::method(@bar)
b: @foo()
EOD
, $adapter->dump($data));


// _
$data = $adapter->load(Tester\FileMock::create('
- Class(arg1, _, [_])
', 'neon'));

Assert::equal(
	[new Statement('Class', ['arg1', 2 => ['_']])],
	$data
);
