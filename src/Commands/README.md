# Laravel代码生成器

读取使用swagger编写的api文档，生成相应的模型，资源，控制器，路由

`php artisan docs-to-code:gen ./docs/your-openapi.yaml --ignored-schema-regular=^Error* --all --force`

## Model

## Resource
根据不同类型的schema生成相应的api资源代码

- 普通模型
  
  ```
    Model1:
      type: object
      properties:
        id:
          type: integer
        field1:
          type: integer
        field2:
          type: string
  ```
  
  生成的代码
```
    'id' => (int)$this->id,
    'field1' => (int)$this->field1,
    'field2' => (string)$this->field2,
```

- 模型字段引用其他模型
  ```
    Model2:
      type: object
      properties:
        id:
          type: integer
        field1:
          type: integer
        field2:
          type: array
          items:
            $ref: '#/components/schemas/Model1'
  ```
  
  引用字段类型（field2）是array，转换的代码为 
```
    'id' => (int)$this->id,
    'field1' => (int)$this->field1,
    'field2' => Model1::collection($this->field2),
```

    引用字段类型是object，转换的代码为 
```
    'id' => (int)$this->id,
    'field1' => (int)$this->field1,
    'field2' => new Model1($this->field2),
```

- 继承模型
```
    Model3:
      allOf:
        - $ref: '#/components/schemas/Model1'
        - type: object
          properties:
            id:
              type: integer
            field1:
              type: integer
```

## Controller
根据operationId创建控制器，方法，再根据http动词下的定义生成对应的方法参数

- 方法名 参数 注释

## Route
...
