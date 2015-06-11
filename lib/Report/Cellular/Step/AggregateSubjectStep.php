<?php

/*
 * This file is part of the PHP Bench package
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpBench\Report\Cellular\Step;

use DTL\Cellular\Workspace;
use DTL\Cellular\Table;
use DTL\Cellular\Calculator;

class AggregateSubjectStep extends AggregateRunStep
{
    public function step(Workspace $workspace)
    {
        $workspace
            ->partition(function (Table $table) {
                return $table->getAttribute('class') . $table->getAttribute('subject');
            })
            ->aggregate(function (Workspace $workspace, $newWorkspace) {
                if (!$workspace->first()) {
                    return;
                }

                if (!isset($newWorkspace[0])) {
                    $newTable = $newWorkspace->createAndAddTable();
                } else {
                    $newTable = $newWorkspace->first();
                }

                $table = $workspace->first();
                $row = $newTable->createAndAddRow();
                $row->set('iters', $table->count(), array('#iter'));
                $row->set('class', $table->getAttribute('class'), array('#class'));
                $row->set('subject', $table->getAttribute('subject'), array('#subject'));
                $row->set('description', $table->getAttribute('description'), array('#description'));
                $row->set('time', Calculator::mean($table->getColumn('time')), array('hidden'));

                $this->applyAggregation($table, $row);
            });

        $table = $workspace->getTable(0);
        $subjectNames = $table->evaluate(function ($row, $names) {
            $names[] = $row['subject']->getValue();

            return $names;
        }, array());
        $table->setTitle('Aggregation by subject');
        $table->setDescription(sprintf(
            implode(', ', $subjectNames)
        ));
    }
}