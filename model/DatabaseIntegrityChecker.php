<?php

class DatabaseIntegrityChecker
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function check(int $sample_limit = 20): array
    {
        $sample_limit = max(1, min(100, $sample_limit));
        $results = [];

        foreach ($this->relations() as $relation) {
            $child_table = table_by_key($relation['child_table']);
            $parent_table = table_by_key($relation['parent_table']);
            $child_column = $relation['child_column'];
            $parent_column = $relation['parent_column'];

            $join = " FROM $child_table child_record" .
                " LEFT JOIN $parent_table parent_record" .
                " ON child_record.$child_column = parent_record.$parent_column";
            $where = " WHERE parent_record.$parent_column IS NULL" . $relation['extra_where'];

            $count = db_query_one("SELECT COUNT(*) AS orphan_count$join$where", $relation['params']);
            $orphan_count = intval($count['orphan_count'] ?? 0);
            $sample_values = [];

            if ($orphan_count > 0) {
                $rows = db_query_all(
                    "SELECT DISTINCT child_record.$child_column AS orphan_value" .
                    "$join$where ORDER BY child_record.$child_column LIMIT $sample_limit",
                    $relation['params']
                );
                $sample_values = array_column($rows, 'orphan_value');
            }

            $results[] = array_merge($relation, [
                'orphan_count' => $orphan_count,
                'sample_values' => $sample_values,
            ]);
        }

        return $results;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function relations(): array
    {
        return [
            [
                'child_table' => 'fetchmail',
                'child_column' => 'mailbox',
                'parent_table' => 'mailbox',
                'parent_column' => 'username',
                'extra_where' => '',
                'params' => [],
                'recommendation' => 'Review whether each fetchmail entry should be removed or assigned to an existing mailbox.',
            ],
            [
                'child_table' => 'quota',
                'child_column' => 'username',
                'parent_table' => 'mailbox',
                'parent_column' => 'username',
                'extra_where' => '',
                'params' => [],
                'recommendation' => 'Review the mailbox state and use the configured quota backend tooling before changing quota data.',
            ],
            [
                'child_table' => 'quota2',
                'child_column' => 'username',
                'parent_table' => 'mailbox',
                'parent_column' => 'username',
                'extra_where' => '',
                'params' => [],
                'recommendation' => 'Review the mailbox state and use the configured quota backend tooling before changing quota data.',
            ],
            [
                'child_table' => 'vacation',
                'child_column' => 'email',
                'parent_table' => 'mailbox',
                'parent_column' => 'username',
                'extra_where' => '',
                'params' => [],
                'recommendation' => 'Review whether each vacation entry should be removed or belongs to an existing mailbox.',
            ],
            [
                'child_table' => 'domain_admins',
                'child_column' => 'domain',
                'parent_table' => 'domain',
                'parent_column' => 'domain',
                'extra_where' => ' AND child_record.domain <> ?',
                'params' => ['ALL'],
                'recommendation' => 'Review whether each domain assignment should be removed or linked to an existing domain.',
            ],
            [
                'child_table' => 'domain_admins',
                'child_column' => 'username',
                'parent_table' => 'admin',
                'parent_column' => 'username',
                'extra_where' => '',
                'params' => [],
                'recommendation' => 'Review whether each domain assignment should be removed or linked to an existing administrator.',
            ],
        ];
    }
}
