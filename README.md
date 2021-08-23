# swagger-laravel-code-generator
根据swagger文档自动生成laravel模型，资源，控制器，路由

## 安装

[Packagist](https://packagist.org/packages/kaxiluo/swagger-laravel-code-generator)

```bash
composer require kaxiluo/swagger-laravel-code-generator
```

## 使用方法

`php artisan swagger-to-code:gen ./docs/your-openapi.yaml --ignored-schema-regular=^Error* --all --force`

参数：
- yaml文件的相对路径（相对于工程根目录）
- 可选参数 `--resource --controller --route --ignored-schema-regular= --force --all`

~ 运行一个例子试试
`php artisan swagger-to-code:gen ./vendor/kaxiluo/swagger-laravel-code-generator/example-swagger/example-openapi.yaml --ignored-schema-regular=^Error* --all`

### 模型 && 资源

根据文档中的 `schemas` 生成对应的模型和资源类，默认不会生成User模型

可选参数：`--ignored-schema-regular=`

参数说明：忽略文档中模型的正则表达式，示例中 `--ignored-schema-regular=^Error*` 表示不生成以Error开头的模型和资源

举个栗子，如果swagger中定义有如下schema：

```yaml
Article:
  type: object
  properties:
    id:
      type: integer
    title:
      type: string
      title: 文章标题
    cover:
      title: 文章封面图
      type: string
      format: uri
    published_time:
      type: string
      title: 文章发布时间
    author:
      $ref: '#/components/schemas/Author'
ArticleDetail:
  allOf:
    - $ref: '#/components/schemas/Article'
    - type: object
      properties:
        description:
          type: string
          title: html描述
        comments:
          type: array
          title: 文章评论
          items:
            $ref: '#/components/schemas/Comment'
Comment:
  type: object
  properties:
    nickname:
      type: string
      title: 评论者昵称
    content:
      type: string
      title: 评论内容
Author:
  type: object
  properties:
    id:
      type: integer
    nickname:
      type: string
      title: 作者昵称
```

生成的模型：`Article` `Comment` `Author`

生成的资源：

`app/Http/Resources/ArticleResource.php`
```phpt
public function toArray($request)
{
    return [
        'id' => (int)$this->id,
        'title' => (string)$this->title,
        'cover' => (string)$this->cover,
        'published_time' => (string)$this->published_time,
        'author' => new AuthorResource($this->author),
    ];
}
```

`app/Http/Resources/AuthorResource.php`

```phpt
public function toArray($request)
{
    return [
        'id' => (int)$this->id,
        'nickname' => (string)$this->nickname,
    ];
}
```

`app/Http/Resources/ArticleDetailResource.php`

```phpt
class ArticleDetailResource extends ArticleResource
{
    public function toArray($request)
    {
        $baseInfo = parent::toArray($request);
        return array_merge($baseInfo, [
			'description' => (string)$this->description,
			'comments' => CommentResource::collection($this->comments),
        ]);
    }
}
```

`app/Http/Resources/CommentResource.php`

```phpt
public function toArray($request)
{
    return [
        'nickname' => (string)$this->nickname,
        'content' => (string)$this->content,
    ];
}
```

### 控制器 && 路由

根据文档中 `paths` 定义的 `operationId` 生成对应的控制器和路由，如果没有定义`operationId`将不会生成控制器和路由

举个栗子，如果swagger中定义

```yaml
/articles:
  get:
      summary: '获取文章列表'
      operationId: Article/ArticleController@index
```

生成的控制器：

`app/Controllers/Article/ArticleController.php` , 类中包含`index`方法

生成的路由：

`Route::get('/articles', 'Article\ArticleController@index');`
