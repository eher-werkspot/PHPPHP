<?php

namespace PHPPHP\Engine;

class Compiler {

    protected $operators = array(
        'Arg' => array('ArrayOp', 'value'),

        // scalars
        'Name'           => array('ScalarOp', 'parts', '\\'),
        'Scalar_DNumber' => array('ScalarOp'),
        'Scalar_LNumber' => array('ScalarOp'),
        'Scalar_String'  => array('ScalarOp'),

        // unary operators
        'Expr_Eval'       => array('UnaryOp', 'PHPPHP\Engine\OpLines\EvalOp', 'expr'),
        'Expr_BooleanNot' => array('UnaryOp', 'PHPPHP\Engine\OpLines\BooleanNot'),
        'Expr_BitwiseNot' => array('UnaryOp', 'PHPPHP\Engine\OpLines\BitwiseNot'),
        'Expr_Isset'      => array('UnaryOp', 'PHPPHP\Engine\OpLines\IssetOp', 'vars'),
        'Expr_PostInc'    => array('UnaryOp', 'PHPPHP\Engine\OpLines\PostInc', 'var'),
        'Expr_Variable'   => array('UnaryOp', 'PHPPHP\Engine\OpLines\FetchVariable', 'name'),
        'Expr_ConstFetch' => array('UnaryOp', 'PHPPHP\Engine\OpLines\FetchConstant', 'name'),
        'Stmt_Echo'       => array('UnaryOp', 'PHPPHP\Engine\OpLines\EchoOp', 'exprs'),
        'Stmt_Return'     => array('UnaryOp', 'PHPPHP\Engine\OpLines\ReturnOp'),

        // binary operators
        'Expr_Assign'         => array('BinaryOp', 'PHPPHP\Engine\OpLines\Assign', 'var', 'expr'),
        'Expr_AssignConcat'   => array('BinaryOp', 'PHPPHP\Engine\OpLines\AssignConcat', 'var', 'expr'),

        'Expr_BooleanAnd'     => array('BinaryOp', 'PHPPHP\Engine\OpLines\BooleanAnd'),
        'Expr_BooleanOr'      => array('BinaryOp', 'PHPPHP\Engine\OpLines\BooleanOr'),
        'Expr_Smaller'        => array('BinaryOp', 'PHPPHP\Engine\OpLines\Smaller'),
        'Expr_SmallerOrEqual' => array('BinaryOp', 'PHPPHP\Engine\OpLines\SmallerOrEqual'),
        'Expr_Greater'        => array('BinaryOp', 'PHPPHP\Engine\OpLines\Smaller', 'right', 'left'),
        'Expr_GreaterOrEqual' => array('BinaryOp', 'PHPPHP\Engine\OpLines\SmallerOrEqual', 'right', 'left'),
        'Expr_Equal'          => array('BinaryOp', 'PHPPHP\Engine\OpLines\Equal'),
        'Expr_NotEqual'       => array('BinaryOp', 'PHPPHP\Engine\OpLines\NotEqual'),
        'Expr_Identical'      => array('BinaryOp', 'PHPPHP\Engine\OpLines\Identical'),
        'Expr_NotIdentical'   => array('BinaryOp', 'PHPPHP\Engine\OpLines\NotIdentical'),
        'Expr_Plus'           => array('BinaryOp', 'PHPPHP\Engine\OpLines\Add'),
        'Expr_Minus'          => array('BinaryOp', 'PHPPHP\Engine\OpLines\Sub'),
        'Expr_Mul'            => array('BinaryOp', 'PHPPHP\Engine\OpLines\Multiply'),
        'Expr_Div'            => array('BinaryOp', 'PHPPHP\Engine\OpLines\Div'),
        'Expr_Mod'            => array('BinaryOp', 'PHPPHP\Engine\OpLines\Mod'),
        'Expr_Concat'         => array('BinaryOp', 'PHPPHP\Engine\OpLines\Concat'),
        'Expr_BitwiseAnd'     => array('BinaryOp', 'PHPPHP\Engine\OpLines\BitwiseAnd'),
        'Expr_BitwiseOr'      => array('BinaryOp', 'PHPPHP\Engine\OpLines\BitwiseOr'),
        'Expr_BitwiseXor'     => array('BinaryOp', 'PHPPHP\Engine\OpLines\BitwiseXor'),
        'Expr_ShiftLeft'      => array('BinaryOp', 'PHPPHP\Engine\OpLines\ShiftLeft'),
        'Expr_ShiftRight'     => array('BinaryOp', 'PHPPHP\Engine\OpLines\ShiftRight'),

        'Expr_FuncCall'       => array('BinaryOp', 'PHPPHP\Engine\OpLines\FunctionCall', 'name', 'args'),
        'Expr_Include'        => array('BinaryOp', 'PHPPHP\Engine\OpLines\IncludeOp', 'type', 'expr'),
    );

    public function compile(array $ast, Zval $returnContext = null) {
        $opArray = array();
        foreach ($ast as $node) {
            $opArray = array_merge($opArray, $this->compileNode($node, $returnContext));
        }
        return $opArray;
    }

    protected function compileNode(\PHPParser_Node $node, Zval $returnContext = null) {
        $nodeType = $node->getType();
        if (isset($this->operators[$nodeType])) {
            return call_user_func_array(
                array($this, 'compile' . $this->operators[$nodeType][0]),
                array_merge(array($node, $returnContext), array_slice($this->operators[$nodeType], 1))
            );
        }

        $methodName = 'compile_' . $nodeType;
        if (!method_exists($this, $methodName)) {
            throw new \Exception($nodeType . ' not supported yet');
        }

        return call_user_func(array($this, 'compile_' . $nodeType), $node, $returnContext);
    }

    protected function compileChild(\PHPParser_Node $node, $childName, $returnContext = null) {
        $childNode = $node->$childName;
        if (is_null($childNode)) {
            return array();
        }
        if (!is_array($childNode)) {
            $childNode = array($childNode);
        }
        if ($returnContext && count($childNode) === 1 && is_scalar($childNode[0])) {
            $returnContext->value = $childNode[0];
            $returnContext->type = Zval::IS_STRING;
            return array();
        }
        return $this->compile($childNode, $returnContext);
    }

    protected function compileArrayOp($node, $returnContext, $left = 'left') {
        $op1 = Zval::ptrFactory();
        $ops = $this->compileChild($node, $left, $op1);
        if ($returnContext) {
            $returnContext->zval->value[] = $op1;
        }
        return $ops;
    }

    protected function compileBinaryOp($node, $returnContext, $handler, $left = 'left', $right = 'right') {
        $op1 = Zval::ptrFactory();
        $op2 = Zval::ptrFactory();
        $ops = $this->compileChild($node, $left, $op1);
        $ops = array_merge($ops, $this->compileChild($node, $right, $op2));
        $opLine = new $handler($op1, $op2);
        if ($returnContext) {
            $opLine->result = $returnContext;
        } else {
            $opLine->result = Zval::ptrFactory();
        }
        $ops[] = $opLine;
        return $ops;
    }

    protected function compileUnaryOp($node, $returnContext, $handler, $left = 'expr') {
        $op1 = Zval::ptrFactory();
        $ops = $this->compileChild($node, $left, $op1);
        $opLine = new $handler($op1);
        if ($returnContext) {
            $opLine->result = $returnContext;
        } else {
            $opLine->result = Zval::ptrFactory();
        }
        $ops[] = $opLine;
        return $ops;
    }

    protected function compileScalarOp($node, $returnContext = null, $name = 'value', $sep = '') {
        if ($returnContext) {
            if ($sep) {
                $returnContext->value = implode($sep, $node->$name);
            } else {
                $returnContext->value = $node->$name;
            }
            $returnContext->rebuildType();
        }
        return array();
    }

    protected function compile_Param($node, $returnContext) {
        $defaultPtr = Zval::ptrFactory();
        $ops = $this->compileChild($node, 'default', $defaultPtr);
        if ($returnContext) {
            $returnContext->zval->value[] = array(
                'name' => $node->name,
                'default' => $defaultPtr,
                'ops' => $ops,
                'isRef' => $node->byRef,
                'type' => $node->type,
            );
        }
        return array();
    }

    protected function compile_Expr_Array($node, $returnContext = null) {
        $ops = array();
        if ($returnContext) {
            $returnContext->type = Zval::IS_ARRAY;
            $returnContext->value = array();
            foreach ($node->items as $subNode) {
                $ops = array_merge($ops, $this->compileNode($subNode, $returnContext));
            }
        }
        return $ops;
    }

    protected function compile_Expr_ArrayItem($node, $returnContext = null) {
        if (!$returnContext) return array();

        $keyPtr = Zval::ptrFactory();
        $ops = $this->compileChild($node, 'key', $keyPtr);
        $valuePtr = Zval::ptrFactory();

        $ops = array_merge($ops, $this->compileChild($node, 'value', $valuePtr));

        $ops[] = new OpLines\AddArrayElement($keyPtr, $valuePtr, $returnContext);

        return $ops;
    }

    protected function compile_Expr_Ternary($node, $returnContext = null) {
        $op1 = Zval::ptrFactory();
        $ops = $this->compileChild($node, 'cond', $op1);

        // Jump targets: midOp is after the first if branch, endOp is after all branches
        $midOp = new OpLines\NoOp;
        $endOp = new OpLines\NoOp;

        $ops[] = new OpLines\JumpIfNot($op1, $midOp);

        $ifAssign = Zval::ptrFactory();
        $ops = array_merge($ops, $this->compileChild($node, 'if', $ifAssign));
        $ops[] = new OpLines\Assign($returnContext, $ifAssign);
        $ops[] = new OpLines\JumpTo($endOp);

        $ops[] = $midOp;
        $elseAssign = Zval::ptrFactory();
        $ops = array_merge($ops, $this->compileChild($node, 'else', $elseAssign));
        $ops[] = new OpLines\Assign($returnContext, $elseAssign);
        $ops[] = $endOp;

        return $ops;
    }

    protected function compile_Scalar_Encapsed($node, $returnContext = null) {
        $ops = array();
        $returnContext = $returnContext ?: Zval::ptrFactory();
        foreach ($node->parts as $part) {
            if (is_string($part)) {
                $ops[] = new OpLines\AssignConcat($returnContext, Zval::ptrFactory($part));
            } else {
                $ret = Zval::ptrFactory();
                $ops = array_merge($ops, $this->compileNode($part, $ret));
                $ops[] = new OpLines\AssignConcat($returnContext, $ret);
            }
        }
        return $ops;
    }


    protected function compile_Stmt_Function($node) {
        $stmts = $this->compileChild($node, 'stmts');
        $namePtr = Zval::ptrFactory();
        $ops = $this->compileChild($node, 'name', $namePtr);
        $paramsPtr = Zval::ptrFactory();
        $ops = array_merge($ops, $this->compileChild($node, 'params', $paramsPtr));

        $ops[] = new OpLines\FunctionDef(array(
            'name' => $namePtr,
            'stmts' => $stmts,
            'params' => $paramsPtr,
        ));

        return $ops;
    }

    protected function compile_Stmt_If($node) {
        $op1 = Zval::ptrFactory();
        $ops = $this->compileChild($node, 'cond', $op1);

        // Jump targets: midOp is after the first if branch, endOp is after all branches
        $midOp = new OpLines\NoOp;
        $endOp = new OpLines\NoOp;

        $ops[] = new OpLines\JumpIfNot($op1, $midOp);

        $ops = array_merge($ops, $this->compileChild($node, 'stmts'));

        $ops[] = new OpLines\JumpTo($endOp);

        $ops[] = $midOp;

        $elseifs = $node->elseifs;
        foreach ($elseifs as $child) {
            $op1 = Zval::ptrFactory();
            $ops = array_merge($ops, $this->compileChild($child, 'cond', $op1));

            $midOp = new OpLines\NoOp;

            $ops[] = new OpLines\JumpIfNot($op1, $midOp);

            $ops = array_merge($ops, $this->compileChild($child, 'stmts'));

            $ops[] = new OpLines\JumpTo($endOp);

            $ops[] = $midOp;
        }

        $else = $node->else;
        if ($else) {
            $ops = array_merge($ops, $this->compileChild($node->else, 'stmts'));
        }

        $ops[] = $endOp;

        return $ops;
    }

    protected function compile_Stmt_While($node) {
        $op1 = Zval::ptrFactory();
        $ops = $this->compileChild($node, 'cond', $op1);

        $endOp = new OpLines\NoOp;
        $ops[] = new OpLines\JumpIfNot($op1, $endOp);

        $whileOps = $this->compileChild($node, 'stmts');
        $ops = array_merge($ops, $whileOps);

        // jump back to cond
        $ops[] = new OpLines\JumpTo($ops[0]);

        $ops[] = $endOp;

        return $ops;
    }

    protected function compile_Stmt_Do($node) {
        $op1 = Zval::ptrFactory();

        $ops = $this->compileChild($node, 'stmts');
        $ops = array_merge($ops, $this->compileChild($node, 'cond', $op1));
        $ops[] = new OpLines\JumpIf($op1, $ops[0]);

        return $ops;
    }

    protected function compile_Stmt_InlineHtml($node) {
        return array(
            new OpLines\EchoOp(Zval::ptrFactory($node->value))
        );
    }
}