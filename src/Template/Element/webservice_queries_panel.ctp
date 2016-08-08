<?php
$actions = [
    \Muffin\Webservice\Query::ACTION_CREATE => __d('muffin/webservice', 'Create'),
    \Muffin\Webservice\Query::ACTION_READ => __d('muffin/webservice', 'Read'),
    \Muffin\Webservice\Query::ACTION_UPDATE => __d('muffin/webservice', 'Update'),
    \Muffin\Webservice\Query::ACTION_DELETE => __d('muffin/webservice', 'Delete'),
];
?>

<table cellspacing="0" cellpadding="0">
    <thead>
    <tr>
        <th><?= __d('debug_kit', 'Endpoint') ?></th>
        <th><?= __d('debug_kit', 'Webservice') ?></th>
        <th><?= __d('debug_kit', 'Alias') ?></th>
        <th><?= __d('debug_kit', 'Action') ?></th>
        <th><?= __d('debug_kit', 'Query') ?></th>
        <th><?= __d('debug_kit', 'Results') ?></th>

        <th><?= __d('debug_kit', 'Took (ms)') ?></th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($queries as $query): ?>
        <tr>
            <td><?= h($query['endpoint']) ?></td>
            <td><?= h($query['webservice']) ?></td>
            <td><?= h($query['alias']) ?></td>
            <td><?= h($actions[$query['action']]) ?></td>
            <td><?= $this->Toolbar->makeNeatArray([
                'conditions' => $query['where'],
                'options' => $query['options'],
                'offset' => $query['offset'],
                'page' => $query['page'],
                'limit' => $query['limit'],
                'sort' => $query['sort'],
            ]) ?></td>
            <td><?= h($query['results']) ?></td>
            <td><?= h($query['took']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
