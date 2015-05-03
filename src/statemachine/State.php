<?php
namespace izzum\statemachine;
use izzum\command\ICommand;
use izzum\command\Null;
use izzum\statemachine\Exception;
/**
 * This class holds the finite state data:
 * - the name of the state
 * - the type of the state (initial/normal/final)
 * - what outgoing transitions this state has (bidirectional association initiated by a Transition)
 * 
 * TRICKY: a State instance can (and should) be shared by multiple Transition
 * objects when it is the same Staet for their origin/from State.
 * The LoaderArray class automatically takes care of this for us.
 * 
 * the order of Transitions *might* be important.
 * whenever a State is asked for it's transitions, the first transition might
 * be tried first. this might have performance and configuration benefits.
 * 
 * @author Rolf Vreijdenberger
 *
 */
class State {
    
     /**
     * state name if it is unknown (not configured)
     * @var string
     */
    const STATE_UNKNOWN = 'unknown';
    /**
     * default name for the first/only initial state
     * @var string
     */
    const STATE_NEW     = 'new';
    /**
     * default name for a normal final state
     * @var string
     */
    const STATE_DONE    = 'done';
    
    /**
     * default exit/entry command
     * @var string
     */
    const COMMAND_NULL = 'izzum\command\Null';
    
    /**
     * default exit/entry command for constructor
     * @var string
     */
    const COMMAND_EMPTY= '';
    
    /**
     * the state types:
     *  - a statemachine has exactly 1 initial type, this is always the only 
     *      entrance into the statemachine.
     *  - a statemachine can have 0-n normal types.
     *  - a statemachine should have at least 1 final type where it has no 
     *      further transitions.
     * @var string
     */
    const
        TYPE_INITIAL = 'initial',
        TYPE_NORMAL  = 'normal',
        TYPE_FINAL   = 'final'
    ;
    

    
     /**
     * The state type:
     * - State::TYPE_INITIAL
     * - State::TYPE_NORMAL
     * - State::TYPE_FINAL
     *
     * @var string
     */
    protected $type;

    /**
     * an array of transitions that are outgoing for this state.
     * These will be set by Transition objects (they provide the association)
     * 
     * this is not a hashmap, so the order of Transitions *might* be important.
     * whenever a State is asked for it's transitions, the first transition might
     * be tried first. this might have performance and configuration benefits
     * 
     * @var Transition[]
     */
    protected $transitions;

    /**
     * The name of the state
     * @var string
     */
    protected $name;
    
    /**
     * fully qualified command name for the command to be executed
     * when entering a state as part of a transition.
     * @var string
     */
    protected $command_entry_name;
    
    /**
     * fully qualified command name for the command to be executed
     * when exiting a state as part of a transition
     * @var string
     */
    protected $command_exit_name;
   
    /**
     * a description for the state
     * @var string
     */
    protected $description;
    
     /**
     * 
     * @param string $name the name of the state
     * @param string $type the type of the state
     */
    public function __construct($name, $type = self::TYPE_NORMAL, $command_entry_name = self::COMMAND_EMPTY, $command_exit_name = self::COMMAND_EMPTY)
    {
        $this->name        			= $name;
        $this->type        			= $type;
        $this->command_entry_name 	= $command_entry_name;
        $this->command_exit_name 	= $command_exit_name;
        $this->transitions 			= array();
        
    }

    /**
     * is it an initial state
     * @return boolean
     */
    public function isInitial()
    {
        return $this->type === self::TYPE_INITIAL;
    }
    
    /**
     * is it a normal state
     * @return boolean
     */
     public function isNormal()
    {
        return $this->type === self::TYPE_NORMAL;
    }

    /**
     * is it a final state
     * @return boolean
     */
    public function isFinal()
    {
        return $this->type === self::TYPE_FINAL;
    }

    /**
     * get the state type
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * add an outgoing transition from this state.
     * 
     * TRICKY: this method should be package visibility only,
     * so don't use directly. it is used to set the bidirectional association
     * for State and Transition from a Transition instance
     * 
     * @param Transition $transition
     * @return boolan yes in case the transition was not on the State already
     */
    public function addTransition(Transition $transition)
    {
    	$output = true;
        //check all existing transitions.
        if($this->hasTransition($transition->getName())) {
            $output = false;
        }

        $this->transitions[] = $transition;
        return $output;
    }


    /**
     * get all outgoing transitions
     * @return Transition[] an array of transitions
     */
    public function getTransitions()
    {
        //a subclass might return an ordered/prioritized array
        return $this->transitions;
    }


    /**
     * gets the name of this state
     */
    public function getName()
    {
        return $this->name;
    }


    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getName();
    }
    
    /**
     * Do we have a transition from this state with a certain name?
     * @param string $transition_name
     * @return boolean
     */
    public function hasTransition($transition_name)
    {
        $has = false;
        foreach($this->transitions as $transition) {
            if($transition_name === $transition->getName()) {
                $has = true;
                break;
            }
        }
        return $has;
    }
    
    /**
     * An action executed every time a state is entered.
     * An entry action will not be executed for an 'initial' state.
     * 
     * @param Context $context
     * @throws Exception
     */
    public function entryAction(Context $context)
    {
   		$command = $this->getCommand($this->getEntryCommandName(), $context);
   		$this->execute($command);
    }
    
    
    /**
     * An action executed every time a state is exited.
     * An exit action will not be executed for a 'final' state since a machine
     * will not leave a 'final' state.
     * 
     * @param Context $context
     * @throws Exception
     */
    public function exitAction(Context $context)
    {
    	$command = $this->getCommand($this->getExitCommandName(), $context);
    	$this->execute($command);
    }

    /**
     * helper method
     * @param ICommand $command
     * @throws Exception
     */
    protected function execute(ICommand $command)
    {
    	try {
    		$command->execute();
    	} catch (\Exception $e) {
    		//command failure
    		$e = new Exception($e->getMessage(), Exception::COMMAND_EXECUTION_FAILURE, $e);
    		throw $e;
    	}
    }
    
    /**
     * returns the associated Command for the entry/exit action.
     * the Command will be configured with the 'reference' of the stateful object
     *
     * @param string $command_name entry or exit command name
     * @param Context $context
     * @return ICommand
     * @throws Exception
     */
    protected function getCommand($command_name, Context $context)
    {
    	$reference = $context->getEntity();
    
    	//it's oke to have no command
    	if($command_name === self::COMMAND_EMPTY || $command_name === null) {
    		//return a command without side effects
    		$command_name = self::COMMAND_NULL;
    	}
    
    	if(class_exists($command_name)) {
    		try {
    			$command = new $command_name($reference);
    		} catch (\Exception $e) {
    			$e = new Exception(
    					sprintf("Command objects to construction with reference: (%s) for Context (%s). message: %s",
    							$command_name, $context->toString(), $e->getMessage()),
    					Exception::COMMAND_CREATION_FAILURE);
    			throw $e;
    		}
    	} else {
    		//misconfiguration
    		$e = new Exception(
    				sprintf("failed command creation, class does not exist: (%s) for Context (%s)",
    						$command_name, $context->toString()),
    				Exception::COMMAND_CREATION_FAILURE);
    		throw $e;
    	}
    	return $command;
    }
    
    
    /**
     * get the fully qualified command name for entry of the state
     * @return string
     */
    public function getEntryCommandName()
    {
    	return $this->command_entry_name;
    }
    
    /**
     * get the fully qualified command name for entry of the state
     * @return string
     */
    public function getExitCommandName()
    {
    	return $this->command_exit_name;
    }
    
    /**
     * set the description of the state (for uml generation for example)
     * @param string $description
     */
    public function setDescription($description)
    {
    	$this->description = $description;
    }
    
    /**
     * get the description for this state (if any)
     * @return string
     */
    public function getDescription()
    {
    	return $this->description;
    }
    
}