jQuery(document).ready(function ($) {
  var CreditCardValidation = {
    // takes the form field value and returns true on valid number
    checkCredit: function valid_credit_card(value) {
      if (!(value.length > 0)) return false;
      if (/[^0-9-\s]+/.test(value)) return false; // accept only digits, dashes or spaces
      var nCheck = 0,
        nDigit = 0,
        bEven = false;
      value = value.replace(/\D/g, "");
      for (var n = value.length - 1; n >= 0; n--) {
        var cDigit = value.charAt(n),
          nDigit = parseInt(cDigit, 10);
        if (bEven) {
          if ((nDigit *= 2) > 9) nDigit -= 9;
        }
        nCheck += nDigit;
        bEven = !bEven;
      }
      return nCheck % 10 == 0;
    },
    getCardFlag: function (cardnumber) {
      var cardnumber = cardnumber.replace(/[^0-9]+/g, "");
      var cards = {
        elo: /^401178|^401179|^431274|^438935|^451416|^457393|^457631|^457632|^504175|^627780|^636297|^636369|^636368|^(506699|5067[0-6]\d|50677[0-8])|^(50900\d|5090[1-9]\d|509[1-9]\d{2})|^65003[1-3]|^(65003[5-9]|65004\d|65005[0-1])|^(65040[5-9]|6504[1-3]\d)|^(65048[5-9]|65049\d|6505[0-2]\d|65053[0-8])|^(65054[1-9]|6505[5-8]\d|65059[0-8])|^(65070\d|65071[0-8])|^65072[0-7]|^(65090[1-9]|65091\d|650920)|^(65165[2-9]|6516[6-7]\d)|^(65500\d|65501\d)|^(65502[1-9]|6550[3-4]\d|65505[0-8])|^(65092[1-9]|65097[0-8])|^(6509[0-7]\d)/,
        hipercard: /^(384100|384140|384160|606282|637095|637568|60(?!11))/,
        amex: /^3[47]/,
        mastercard: /^(5[1-5]|677189)|^(222[1-9]|2[3-6]\d{2}|27[0-1]\d|2720)|^(223[1-9]|2[3-6]\d{2}|27[0-1]\d|2720)/,
        visa: /^4/,
      };
      for (var flag in cards) {
        if (cards[flag].test(cardnumber)) {
          return flag;
        }
      }
      return false;
    },
    checkCVV: function (cvv) {
      if (cvv.length == 0) return false;
      if (cvv.length != 3 && cvv.length != 4) return false;
      return true;
    },
    checkName: function (name) {
      if (name.length == 0) return false;
      if (name.length > 50) return false;
      return true;
    },
    checkPhone: function (phone) {
      phone = phone.replace("(", "");
      phone = phone.replace(")", "");
      phone = phone.replace("-", "");
      phone = phone.replace(" ", "");
      var regex = new RegExp(
        "^((1[1-9])|([2-9][0-9]))((3[0-9]{3}[0-9]{4})|(9[0-9]{3}[0-9]{5}))$"
      );
      return regex.test(phone);
    },
    checkCPF: function (cpf) {
      cpf = cpf.replace(/\D/g, "");
      if (cpf.toString().length != 11 || /^(\d)\1{10}$/.test(cpf)) return false;
      var result = true;
      [9, 10].forEach(function (j) {
        var soma = 0,
          r;
        cpf
          .split(/(?=)/)
          .splice(0, j)
          .forEach(function (e, i) {
            soma += parseInt(e) * (j + 2 - (i + 1));
          });
        r = soma % 11;
        r = r < 2 ? 0 : 11 - r;
        if (r != cpf.substring(j, j + 1)) result = false;
      });
      return result;
    },
    checkCNPJ: function (cnpj) {
      if (!cnpj) return false;

      // Aceita receber o valor como string, número ou array com todos os dígitos
      const isString = typeof cnpj === "string";
      const validTypes =
        isString || Number.isInteger(cnpj) || Array.isArray(cnpj);

      // Elimina valor em formato inválido
      if (!validTypes) return false;

      // Filtro inicial para entradas do tipo string
      if (isString) {
        // Limita ao máximo de 18 caracteres, para CNPJ formatado
        if (cnpj.length > 18) return false;

        // Teste Regex para veificar se é uma string apenas dígitos válida
        const digitsOnly = /^\d{14}$/.test(cnpj);
        // Teste Regex para verificar se é uma string formatada válida
        const validFormat = /^\d{2}.\d{3}.\d{3}\/\d{4}-\d{2}$/.test(cnpj);

        // Se o formato é válido, usa um truque para seguir o fluxo da validação
        if (digitsOnly || validFormat) true;
        // Se não, retorna inválido
        else return false;
      }

      // Guarda um array com todos os dígitos do valor
      const match = cnpj.toString().match(/\d/g);
      const numbers = Array.isArray(match) ? match.map(Number) : [];

      // Valida a quantidade de dígitos
      if (numbers.length !== 14) return false;

      // Elimina inválidos com todos os dígitos iguais
      const items = [...new Set(numbers)];
      if (items.length === 1) return false;

      // Cálculo validador
      const calc = (x) => {
        const slice = numbers.slice(0, x);
        let factor = x - 7;
        let sum = 0;

        for (let i = x; i >= 1; i--) {
          const n = slice[x - i];
          sum += n * factor--;
          if (factor < 2) factor = 9;
        }

        const result = 11 - (sum % 11);

        return result > 9 ? 0 : result;
      };

      // Separa os 2 últimos dígitos de verificadores
      const digits = numbers.slice(12);

      // Valida 1o. dígito verificador
      const digit0 = calc(12);
      if (digit0 !== digits[0]) return false;

      // Valida 2o. dígito verificador
      const digit1 = calc(13);
      return digit1 === digits[1];
    },
    checkExpirationDate: function (data) {
      let dtArray = data.split("/");

      if (dtArray == null) return false;

      var dtMonth = dtArray[0];
      var dtYear = dtArray[1];

      if (dtMonth < 1 || dtMonth > 12) return false;

      if (dtYear < new Date().getFullYear() || dtYear > 2050) return false;

      return true;
    },
    checkBirthdayDate: function (data) {
      let dtArray = data.split("/");

      if (dtArray == null) return false;

      var dtDay = dtArray[0];
      var dtMonth = dtArray[1];
      var dtYear = dtArray[2];

      if (dtYear < 1945 || dtYear >= new Date().getFullYear()) return false;

      if (dtMonth < 1 || dtMonth > 12) return false;
      else if (dtDay < 1 || dtDay > 31) return false;
      else if (
        (dtMonth == 4 || dtMonth == 6 || dtMonth == 9 || dtMonth == 11) &&
        dtDay == 31
      )
        return false;
      else if (dtMonth == 2) {
        var isleap =
          dtYear % 4 == 0 && (dtYear % 100 != 0 || dtYear % 400 == 0);
        if (dtDay > 29 || (dtDay == 29 && !isleap)) return false;
      }
      return true;
    },
  };
  var phoneMaskBehavior = function (val) {
    return val.replace(/\D/g, "").length === 11
      ? "(00) 00000-0000"
      : "(00) 0000-00000";
  };
  var phoneMaskOptions = {
    onKeyPress: function (val, e, field, options) {
      field.mask(phoneMaskBehavior.apply({}, arguments), options);
    },
  };
  var documentMaskBehavior = function (val) {
    return val.replace(/\D/g, "").length < 12
      ? "000.000.000-000"
      : "00.000.000/0000-00";
  };
  var documentMaskOptions = {
    onKeyPress: function (val, e, field, options) {
      field.mask(documentMaskBehavior.apply({}, arguments), options);
    },
  };

  $("#belluno_credit_card_phone").mask(phoneMaskBehavior, phoneMaskOptions);
  $("#billing_phone").mask(phoneMaskBehavior, phoneMaskOptions);
  $("#billing_client_cpf").mask(documentMaskBehavior, documentMaskOptions);
  $("#belluno_credit_card_document").mask(documentMaskBehavior, documentMaskOptions);
  $("#belluno_credit_card_security_code").mask("0000");
  $("#billing_postcode").mask("00000-000");
  $("#shipping_postcode").mask("00000-000");
  $("#belluno_credit_card_number").mask("0000 0000 0000 0000");
  $("#belluno_credit_card_expiration").mask("00/0000");
  $("#belluno_credit_card_birthdate").mask("00/00/0000");

  $("#belluno_credit_card_number").on("input", function () {
    $cardNumber = this.value;
    if ($cardNumber.length > 5) {
      $("#belluno_credit_card_number").removeAttr("class");
      let flag;
      flag = CreditCardValidation.getCardFlag($cardNumber);
      if (flag !== false) {
        $("#belluno_credit_card_number").addClass(flag);
        $("#belluno_credit_card_brand").val(flag);
      } else {
        $("#belluno_credit_card_brand").val('');
      }
    } else {
      $("#belluno_credit_card_brand").val('');
    }
  });

});
