# DO NOT USE! THESE LIBRARY DOESN'T HAVE ALL DEPENDENCIES 

# Operator

This library is pretty big so no tutorial here how to use it. If you want to know send me an e-mail mortimer333@vp.pl.
But I will provide example and introduction:

# Introduction

Operator allows you to control the CRUD proccess from one file. You are able to define users form, Events, Filters, Errors, Data Validation, All database actions (insert, update, delete) etc. It uses js class to gather data so you can send it by API with the rest of values. I'm using this in production but only because this library was created for this framework I'm working in. So for now it's not production safe. It uses my other library Vali for validation, not uploaded Widgets for my custom form blocks and other not uploaded Tools for html escaping and few other things. 

# Example

```json
{
  "_events" : {
    "after_set_table" : [
      {
        "type"   : "replace",
        "dir"    : "stages.id",
        "table"  : "stages",
        "value"  : "_fun->class:Class->method:GetMaxStageId"
      },{
        "type"   : "swapVars"
      }
    ]
  },
  "_data" : {
    "stages" : {
      "_conditions" : [
        {
          "error" : "The qualifying number of points in the $ {stages.title} Stage must not be less than 0.",
          "con"    : [
            {
              "var1" : "this.limit_next",
              "oper" : "<",
              "var2" : 0
            }
          ]
        }
      ],
      "stage_id" : {
        "name"   : "Stage ID",
        "typeof" : "int",
        "value"  : "${stages.id}",
        "field"  : "hidden",
        "type"   : "index"
      },
      "stage_type" : {
        "name"   : "Stage Type",
        "typeof" : "int",
        "value"  : 0,
        "field"  : "numeric"
      },
      "comp_id" : {
        "name"   : "Competitions ID",
        "typeof" : "int",
        "value"  : 0,
        "field"  : "numeric"
      },
      "stage_title" : {
        "name"   : "Stage Title",
        "typeof" : "string",
        "value"  : "Title",
        "field"  : "text"
      },
      "stage_desc" : {
        "name"   : "Stage Description",
        "typeof" : "string",
        "value"  : "",
        "field"  : "rich_editor"
      },
      "stage_limit" : {
        "name"   : "Qualifying quantity",
        "typeof" : "decimal",
        "value"  : 0.0,
        "field"  : "numeric"
      },
      "stage_order" : {
        "name"   : "Order",
        "typeof" : "int",
        "value"  : 0,
        "add"    : {"onfocusout":"changeOrder(event.target)"},
        "field"  : "numeric"
      },
      "stage_end" : {
        "name"   : "Stage end",
        "typeof" : "date",
        "value"  : "1970-01-01",
        "field"  : "date"
      }
    }
  },
  "_arch" : {
    "constants item big" : [
      {
        "_html" : "<h1 class='title flexCenter'><span class='line'></span><span class='text'>Stage info</span><span class='line'></span></h1>"
      },
      {
        "_wid" : "stages.id"
      },{
        "doubleInput big" : [
          {
            "_wid" : "stages.title"
          },{
            "_wid" : "stages.limit_next"
          }
        ]
      },{
        "doubleInput big" : [
          {
            "_wid" : "stages.order_pos"
          },{
            "_wid" : "stages.end"
          }
        ]
      },{
        "_wid" : "stages.description"
      }
    ]
  }
}
```

As you can see it allows for defining functions, variable, widgets and more (in this example I have only showed at most half of it's features)
