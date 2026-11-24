////// Lib. //////
//////////////////

import Component from "gia/Component";
// import eventbus from "gia/eventbus"; // NOT NEEDED ATM

///// Util. //////
//////////////////

// ...

///// Init. //////
//////////////////

// import { get_viewportState } from "../../../assets/js/init/init_viewportState";

///// Hdls. //////
//////////////////

// import ebh_window_resize from "./eventbusHandlers/ebh_window_resize.js";

// import stChH_mode from "./stateChangeHandlers/stChH_mode.js";

///// Assets /////
//////////////////

// NOTE ON OPTIONS (gia 1.0.1)
//
// `this.options = {...}` is a SETTER on gia's BaseComponent. Assigning to it
// reads the element's g-options attribute and merges it over the defaults you
// pass. That is how values reach the component.
//
// The constructor's second argument looks like the same thing but is NOT:
// neither gia's loadComponents() nor @swup/gia-plugin ever passes it, so it
// is always undefined. Reading a property off it throws before the component
// can mount, and the failure is silent — the component simply never works.
//
// Declare defaults for every key you expect from g-options, so the shape is
// visible here even when the attribute is absent.

//  Default values for manually extracted options
//  (see constructor, in case specific option has not been provided
//  in comp. config.).

// Uncomment together with the matching `optionKey` line in the constructor.
// const defaultOptions = {
//   optionkey: { foo: "bar" },
// };

// Default log styles

const defaultLogStyles = {
  default: "#6682d6",
  action: "#c7d0ff",
  event: "#97a5ce",
  warning: "#ffaf00",
  error: "#ff3232",
  success: "#00c853",
};

// Comp. class ///
//////////////////

export default class NewComp extends Component {
  /////////// Constructor ////////////
  ////////////////////////////////////

  constructor(el) {
    super(el);

    //// DOM references ////
    ////////////////////////

    this.ref = {
      someRef: [],
    };

    /////// Options ////////
    ////////////////////////

    // Merged with the element's g-options — see the note above.
    this.options = {
      name: "NewComp",
      // someOption: "default",
      logs: false,
      logStyles: defaultLogStyles,
    };

    ////// Init. f() ///////
    ////////////////////////


    /////// Modules ////////
    ////////////////////////

    this.modules = {
      // ...
    };

    ///////// API //////////
    ////////////////////////

    this.api = {
      // ...
    };

    // Eventbus listeners //
    ////////////////////////

    // this.ebl_window_resize = ebh_window_resize.bind(this); // NOT NEEDED ATM

    /// State.-ch. list. ///
    ////////////////////////

    // this.stChL_mode = stChH_mode.bind(this); // NOT NEEDED ATM

    // Custom event hdls. //
    ////////////////////////

    // Note: To be passed to parent class

    // ...

    /// Pre-mount init. ////
    ////////////////////////

    // ...
  }

  ////////////// Mount ///////////////
  ////////////////////////////////////

  mount() {
    if (this.options.logs) console.warn("Mounting:", this.options.name);
    this.init();
  }

  ///////////// Unmount //////////////
  ////////////////////////////////////

  unmount() {
    if (this.options.logs) console.warn("Unmounting:", this.options.name);

    // Eventbus listener de-registration //
    // eventbus.off("window_resize", this.ebl_window_resize); // NOT NEEDED ATM

    // API call de-registration //
    // ...
  }

  ////////////// Init. ///////////////
  ////////////////////////////////////

  /// Base init. ///
  //////////////////

  init() {
    // this.setState({ mode: "init" }); // NOT NEEDED ATM
    // this.init_states(); // NOT NEEDED ATM
    // this.init_eventbus(); // NOT NEEDED ATM
    // this.setState({ mode: "ready" }); // NOT NEEDED ATM
  }

  // States init. //
  //////////////////

  // NOT NEEDED ATM //
  // init_states() {
  //   this.logger("init", ["states"], "action", { eventName: "init_states", inline: true });
  //   this.setState({ features: { mouse: window.matchMedia("(pointer:fine)").matches } });
  // }

  // Events init. //
  //////////////////

  // NOT NEEDED ATM //
  // init_events() {
  //   // Listener registration //
  //   // ...

  //   // ...
  // }

  // Eventb. init. //
  ///////////////////

  // NOT NEEDED ATM //
  // init_eventbus() {
  //   this.logger("init", ["eventbus"], "action", { eventName: "init_eventbus", inline: true });

  //   // Listener registration //
  //   eventbus.on("window_resize", this.ebl_window_resize);

  //   // API call registration //
  //   // ...
  // }

  /////////// State mgmt. ////////////
  ////////////////////////////////////

  stateChange(changes) {
    if (this.options.logs) console.log("State change:", changes);
    // if ("mode" in CHANGES) this.stChL_mode(CHANGES);
  }
}
