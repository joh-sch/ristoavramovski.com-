////// Lib. //////
//////////////////

// ...

///// Utils. /////
//////////////////

// ...

// Event hdls. ///
//////////////////

// ...

///// Assets /////
//////////////////

const msgs = {
  call: (NAME) => [`eventbus call: ${NAME}`],
};

//// Hdl. f() ////
//////////////////

export default function ebh_eventbusCall(ARGS) {
  ///// Setup //////
  //////////////////

  // const { REF } = this.ref;
  const { _name } = ARGS;
  const { logs } = this.options;
  if (logs) console.log(msgs.call(_name)[0]);

  ///// ACTION /////
  //////////////////

  // ...
}
