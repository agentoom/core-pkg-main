<?php

namespace Agentoom\Core\Tests\Unit\Helpers;

use Agentoom\Core\Helpers\Helpers;
use Agentoom\Core\Tests\TestCase;

class JsonExtractorTest extends TestCase
{
    public function test_extracts_clean_json(): void
    {
        $data = Helpers::extractJsonFromText('{"name": "test", "value": 123}');
        $this->assertIsArray($data);
        $this->assertEquals('test', $data['name']);
        $this->assertEquals(123, $data['value']);
    }

    public function test_extracts_clean_json_array(): void
    {
        $data = Helpers::extractJsonFromText('[{"id": 1}, {"id": 2}]');
        $this->assertIsArray($data);
        $this->assertCount(2, $data);
        $this->assertEquals(1, $data[0]['id']);
    }

    public function test_extracts_json_from_markdown_fence(): void
    {
        $text = <<<'TEXT'
Here is the result:

```json
{"name": "Alice", "role": "admin"}
```

Hope that helps!
TEXT;
        $data = Helpers::extractJsonFromText($text);
        $this->assertIsArray($data);
        $this->assertEquals('Alice', $data['name']);
        $this->assertEquals('admin', $data['role']);
    }

    public function test_extracts_json_from_unlabeled_fence(): void
    {
        $text = <<<'TEXT'
The output is:

```
{"status": "ok", "count": 42}
```
TEXT;
        $data = Helpers::extractJsonFromText($text);
        $this->assertIsArray($data);
        $this->assertEquals('ok', $data['status']);
        $this->assertEquals(42, $data['count']);
    }

    public function test_extracts_json_with_ai_commentary(): void
    {
        $text = 'After analyzing the data, here is the result: {"products": [{"name": "Widget", "price": 9.99}]} I hope this meets your requirements.';
        $data = Helpers::extractJsonFromText($text);
        $this->assertIsArray($data);
        $this->assertCount(1, $data['products']);
        $this->assertEquals('Widget', $data['products'][0]['name']);
        $this->assertEquals(9.99, $data['products'][0]['price']);
    }

    public function test_extracts_nested_json_with_commentary(): void
    {
        $text = 'Based on the SEO analysis:
- Checked keywords
- Reviewed competitors

```json
{
    "seo_score": 85,
    "recommendations": [
        "Improve meta description",
        "Add alt text to images"
    ],
    "competitor_data": {
        "top_rank": "example.com",
        "avg_score": 72
    }
}
```

Please review the recommendations.
';
        $data = Helpers::extractJsonFromText($text);
        $this->assertIsArray($data);
        $this->assertEquals(85, $data['seo_score']);
        $this->assertCount(2, $data['recommendations']);
        $this->assertEquals('example.com', $data['competitor_data']['top_rank']);
    }

    public function test_returns_null_for_empty_string(): void
    {
        $this->assertNull(Helpers::extractJsonFromText(''));
        $this->assertNull(Helpers::extractJsonFromText(null));
    }

    public function test_returns_null_for_plain_text_without_json(): void
    {
        $this->assertNull(Helpers::extractJsonFromText('This is just a regular text without any JSON content.'));
    }

    public function test_extracts_from_text_with_multiple_code_blocks(): void
    {
        $text = <<<'TEXT'
First, here is some SQL:
```sql
SELECT * FROM users;
```

And the result:
```json
{"users": [{"id": 1, "name": "John"}], "total": 1}
```
TEXT;
        $data = Helpers::extractJsonFromText($text);
        $this->assertIsArray($data);
        $this->assertEquals(1, $data['total']);
        $this->assertCount(1, $data['users']);
    }

    public function test_extracts_array_from_markdown(): void
    {
        $text = <<<'TEXT'
Here are the items:

```json
[{"sku": "ABC123", "qty": 5}, {"sku": "DEF456", "qty": 3}]
```
TEXT;
        $data = Helpers::extractJsonFromText($text);
        $this->assertIsArray($data);
        $this->assertCount(2, $data);
        $this->assertEquals('ABC123', $data[0]['sku']);
        $this->assertEquals(3, $data[1]['qty']);
    }

    public function test_extracts_first_json_object_when_multiple_exist(): void
    {
        $text = '{"first": true} and then {"second": true}';
        $data = Helpers::extractJsonFromText($text);
        $this->assertIsArray($data);
        $this->assertTrue($data['first']);
        $this->assertArrayNotHasKey('second', $data);
    }

    public function test_handles_json_with_line_breaks_and_extra_whitespace(): void
    {
        $text = "  \n\n  {\"key\": \"value\"}  \n\n  ";
        $data = Helpers::extractJsonFromText($text);
        $this->assertIsArray($data);
        $this->assertEquals('value', $data['key']);
    }
}
