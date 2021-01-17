class SaveStage {
  static var_data = [];

  static getValueFormWidgets(widget, type) {
    let value;
    switch (type) {
      case "numeric":
      case "text":
      case "date":
      case "hidden":
      case "listItem":
        value = widget.value;
        break;
      case "rich_editor":
        value = JSON.stringify(WYSIWYG.Instances[widget.id].get());
        break;
      case "check" :
        value = widget.checked;
        break;
      case "award_script" :
        value = widget.attributes.award_id.value;
        break;
      default:
        console.log(widget);
        throw new InvalidArgumentException("Nie znany typ widgetu - " + type)
    }
    return value;
  }

  static get () {
    const stages      = document.querySelectorAll("#carusel .stage:not(.end)");
    const stages_data = [];

    stages.forEach(stage => {
      const type  = stage.querySelector("input[name='stages.type']").value;
      let stageObj = SaveStage.GetStage(type, stage);
      stages_data.push(stageObj);
    });

    return stages_data;
  }

  static getOld() {
    const stages      = document.querySelectorAll("#carusel .stage:not(.end)");
    const stages_data = [];

    stages.forEach(stage => {
      const type  = stage.querySelector("input[name='stages.type']").value;
      stages_data.push(SaveStage.GetStage(type, stage));
      return;
      SaveStage.var_data = [];

      const _data = _Stages[type];
      stages_data.push({});

      Object.keys(_data).forEach(table => {
        // if array then it's multi type
        if (typeof _data[table]["_multi"] != "undefined") {

          stages_data[stages_data.length - 1][table] = SaveStage.getMulti(_data[table], table, stage);

        } else if (typeof _data[table]['_separator'] != "undefined") {

          stages_data[stages_data.length - 1][table] = {};
          Object.keys(_data[table]['_separator']).forEach(key => {
            stages_data[stages_data.length - 1][table][key] = SaveStage.getMulti(_data[table]['_separator'][key], table, stage, key);
          });

        } else if (typeof _data[table] == "object") {

          stages_data[stages_data.length - 1][table] = {};
          let table_data = stages_data[stages_data.length - 1][table];

          Object.keys(_data[table]).forEach(column => {

            let con = stage.querySelector("[name='" + table + "_" + column + "']");
            if (con) {

              let value = SaveStage.getValueFormWidgets(con, _data[table][column].field);
              if (SaveStage.CheckReq(value,_data[table][column])) {
                let val = SaveStage.validate(value,_data[table][column].typeof,_data[table][column].name,con);
                if (val !== true) SaveStage.throwError(val, con);
                table_data[column] = value;
              }

            } else if (SaveStage.CheckVar(column[0], _data[table][column].value)) {
              SaveStage.var_data.push({
                "direction" : [table,column],
                "var"       : _data[table][column].value
              });
            }

          });

        } else {
          new Message({mes : 'Nie rozpoznany typ danych przy zbieraniu danych z Etapu.'});
        }

      });
      SaveStage.collateVarsByDepend();
      SaveStage.var_data.forEach(function (data) {
        let rVar = SaveStage.getVar(data.var);
        rVar = rVar.split('.');
        let rValue = SaveStage.findValue(stages_data[stages_data.length - 1],rVar);
        SaveStage.setAttr(stages_data[stages_data.length - 1],data.direction,rValue);
      });

    });

    return stages_data;
  }

  static GetStage(type, stage)
  {
    let stage_data = {};
    const _data     = _Stages[type];

    Object.keys(_data).forEach(table => {
      stage_data[table] = SaveStage.GetResolve(_data[table], table, stage, table);
    });
    return stage_data;
  }

  static GetResolve(columns, table, stage, cur_dir)
  {
    let tableAr = {};
    if (typeof columns._multi != "undefined") {
      tableAr = SaveStage.GetMultiValue (stage, cur_dir, columns);
    }
    else {
      let keys = Object.keys(columns);
      for (var i = 0; i < keys.length; i++) {
        let c_name = keys[i];
        let column = columns[keys[i]];

        if (isNaN(c_name) && c_name[0] === "_") continue;
        // console.log("undefined - " + c_name, typeof column.value == "undefined", " is Array", Array.isArray(column), column);
        if (typeof column.value == "undefined" && typeof column === 'object' && column !== null ) tableAr[c_name] = SaveStage.GetResolve(column, c_name, stage, cur_dir + "." + c_name);
        else                                                                                      tableAr[c_name] = SaveStage.GetValue(stage, cur_dir + "." + c_name, column, columns);

      }
    }
    return tableAr;
  }

  static GetMultiValue (stage, cur_dir, table) {
    let list = stage.querySelectorAll(table._class);
    let items = [];

    list.forEach((item, i) => {
      let listObj = {};
      let add     = true;

      Object.keys(table._template).forEach(function (c_name) {
          if (isNaN(c_name) && c_name[0] === "_") return;
          let column = table[c_name];

          let value = SaveStage.GetValue(item, cur_dir + "." + c_name , column, table);
          if (value === undefined && add === true){
            add = false;
            return;
          }

          listObj[c_name] = value;
      });

      if (add) items.push(listObj);
    });

    return items;
  }

  static GetValue(stage, cur_dir, column, table) {
    let con = stage.querySelector("[name='" + cur_dir + "']");

    if (con) {

      let value = SaveStage.getValueFormWidgets(con, column.field);
      if (SaveStage.CheckReq(value, column)) {
        let val = SaveStage.validate(value, column.typeof, column.name,con);
        if (val !== true && typeof table['_possible'] != "undefined" && table['_possible'] == true) return undefined;
        else if (val !== true) SaveStage.throwError(val, con);
        return value;
      } else if (typeof column.value != "undefined") return column.value;

    } else if (SaveStage.CheckVar("", column.value)) {
      SaveStage.var_data.push({
        "direction" : cur_dir.split('.'),
        "var"       : column.value
      });
    }
  }

  static setAttr(data, directions, value)
  {
    if (directions.length > 1) {
      let dir = directions[0];
      if (typeof data[dir] == "undefined") throw new InvalidArgumentException("Nie istnieje taka dana - " + dir);
      SaveStage.setAttr(data[dir], directions.slice(1), value);
    } else data[directions[0]] = value;

  }

  static findValue(data, directions)
  {
    if (directions.length > 1) {
      let dir = directions[0];
      if (typeof data[dir] == "undefined") throw new InvalidArgumentException("Nie istnieje taka dana - " + dir);
      return SaveStage.findValue(data[dir], directions.slice(1));
    } else {
      if (typeof data[directions[0]] != "undefined")
        return data[directions[0]];
      else throw new InvalidArgumentException("Nie istnieje taka dana - " + directions[0]);
    }
  }

  static getVar(variable) {
    let start, end;
    start = variable.indexOf("_{") + 2;
    end   = variable.indexOf("}");
    return variable.slice(start,end);
  }

  /**
  * This function reorders all var_data in a way that even if the variable depends on another variable
  * (which is a var too) will we replaced after the one it depends on. Example:
  * we have : {var : '_{a_table.type}', direction: ['tables','a_table']}
  * and     : {var : '_{b_table.type}', direction: ['a_table','type']  }
  *
  * so we have scenerio when the first var is trying to get value from a_table->type and save it into tables->a_table
  * which currently doesn't exist because it's a variable too which wants to get value from b_table->type and save it to a_table->type
  *
  * so we have to make sure all variables are in right order one after each other so they can get valid value
  */

  static collateVarsByDepend() {
    SaveStage.var_data.forEach(function (data, i) {
      if (i + 1 === SaveStage.var_data.length) return; // we don't check the last one
      let rVar, temp, cVar;
      rVar  = SaveStage.getVar(data.var);
      for (var j = i + 1; j < SaveStage.var_data.length; j++) {
        if (rVar === SaveStage.var_data[j].direction.join('.')) {
          cVar = SaveStage.getVar(SaveStage.var_data[j].var);
          if (cVar === data.direction.join('.'))
            throw new InvalidArgumentException("Dwie zmienne sięgają od siebie nawzajem (" + SaveStage.var_data[j].direction.join('.') + "->" + cVar + "," + data.direction.join('.') + "->" + rVar + "), nie można ustalić wartości");
          temp = SaveStage.var_data[j];
          SaveStage.var_data.splice(j,1);
          SaveStage.var_data.splice(i,0,temp);
          i++;
        }
      }
    });
  }

  static CheckReq(value,column) {
    return value != ""                      &&
           typeof column.req != "undefined" &&
           column.req == false
           ||
           (
             typeof column.req == "undefined"
             ||
             typeof column.req != "undefined" &&
             column.req == true
           )
  }

  static CheckVar(name, value) {
    return name[0] !== "_" && typeof value == "string" && value.includes("_{");
  }

  static getMulti( _data_table, table, stage, key = false) {
    let stages_data = [];

    if (!_data_table._class) console.error(_data_table, "Nie posiada _class pozwalającego na znalezienie wszystkich obiektów.");

    let list = stage.querySelectorAll(_data_table._class);
    list.forEach((listItem,i) => {
      let listObj = {};
      let add     = true;

      Object.keys(_data_table).forEach(function (column) {
        if (!add)              return;
        if (column[0] === "_") return;
        let value,con;

        if (!con && typeof listItem.attributes.name != "undefined" && listItem.attributes.name.value == table + "_" + column)
          con = listItem;
        else
          con = listItem.querySelector("[name='" + table + "_" + column + "']");

        if (con) value = SaveStage.getValueFormWidgets(con, _data_table[column].field);

        if (SaveStage.CheckVar(column, _data_table[column].value)) {
          let direction = [table,i,column];
          if (key) direction = [table,key,i,column];
          SaveStage.var_data.push({
            "direction" : direction,
            "var"       : _data_table[column].value
          });

        } else if (SaveStage.CheckReq(value,_data_table[column])) {
          let val = SaveStage.validate(value, _data_table[column].typeof, _data_table[column].name, con);
          if (val === true) listObj[column] = value;
          else if (typeof _data_table['_possible'] != "undefined" && _data_table['_possible'] == true) {
            add = false;
            return;
          } else {
            if (typeof value == "undefined") throw new InvalidArgumentException("Nie znaleziono wartości dla " + _data_table[column].name);
            else  SaveStage.throwError(val,con);
          }

        } else if(column[0] !== "_") throw new InvalidArgumentException("Nie znaleziono wymaganej danej " + table + "." + column);
      });

      if(add) stages_data.push(listObj);

    });

    return stages_data;
  }

  static validate(value, type, name, node) {
    if (node) node.style.boxShadow = "";
    let error  = false;
    let er_msg = "";
    switch (type) {
      case "empty_string" :
      case "string" :
        if (typeof value != "string") {
          error  = true;
          er_msg = name + " musi być słowem.";
        } else if (value == "" && type != "empty_string") {
          error  = true;
          er_msg = "Pole `" + name + "` nie może być puste.";
        }
        break;
      case "int" :
        console.log("int",name,value);
        if (value == "") {
          error  = true;
          er_msg = "Pole `" + name + "` nie może być puste.";
        } else if (isNaN(value) || parseInt(Number(value)) != value || isNaN(parseInt(value, 10))) {
          error  = true;
          er_msg = name + " nie jest liczbą całkowitą.";
        }
        break;
      case "decimal" :
        if (value == "") {
          error  = true;
          er_msg = "Pole `" + name + "` nie może być puste.";
        } else if (isNaN(value)) {
          error  = true;
          er_msg = name + " nie jest liczbą.";
        }
        break;
      case "bool" :
        if (value != true && value != false) {
          error  = true;
          er_msg = name + " nie jest typu logicznego.";
        }
        break;
      case "date" :
        if (value == "") {
          error  = true;
          er_msg = "Pole `" + name + "` nie może być puste.";
        } else if (!validateDate(value)) {
          error  = true;
          er_msg = name + " nie jest datą.";
        }
        break;
      default:
        throw new InvalidArgumentException("Nie rozpoznany typ: " + type + " w " + name);
    }

    if (error) return er_msg;
    else       return true;

  }

  static throwError(er_msg, node) {
    if (node) {
      node.style.boxShadow = "0 0 5px 2.5px rgba(255,0,0,.25)";
      node.scrollIntoView();
    }
    throw new InvalidArgumentException(er_msg);
  }
}
