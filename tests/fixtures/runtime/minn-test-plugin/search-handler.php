<?php
/**
 * A search handler of the fixture plugin's own (wp_rest_search_handlers):
 * seven numbered items, odd and even, for /wp/v2/search?type=minn-test.
 */
class Minn_Test_Search_Handler extends WP_REST_Search_Handler
{
    public function __construct()
    {
        $this->type = 'minn-test';
        $this->subtypes = ['odd', 'even'];
    }

    public function search_items(WP_REST_Request $request)
    {
        $wanted = (array) $request['subtype'];
        $ids = array_values(array_filter(range(1, 7), static function (int $n) use ($wanted, $request): bool {
            $kind = $n % 2 === 1 ? 'odd' : 'even';
            $search = (string) ($request['search'] ?? '');
            return (in_array('any', $wanted, true) || in_array($kind, $wanted, true)) && ($search === '' || str_contains('item ' . $n, $search));
        }));
        $perPage = (int) $request['per_page'];
        return [self::RESULT_IDS => array_slice($ids, ((int) $request['page'] - 1) * $perPage, $perPage), self::RESULT_TOTAL => count($ids)];
    }

    public function prepare_item($id, array $fields)
    {
        return ['id' => $id, 'title' => 'Item ' . $id, 'url' => home_url('/?minn-test-item=' . $id), 'type' => 'minn-test', 'subtype' => $id % 2 === 1 ? 'odd' : 'even'];
    }

    public function prepare_item_links($id)
    {
        return ['about' => ['href' => home_url('/?minn-test-item=' . $id)]];
    }
}
